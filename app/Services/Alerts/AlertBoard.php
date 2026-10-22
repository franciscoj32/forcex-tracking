<?php

namespace App\Services\Alerts;

use App\Models\Shipment;
use App\Models\ShipmentAlertState;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Alertas del Inicio para un usuario, separadas en activas, vistas y pospuestas.
 *
 * - Customer y Operaciones: las de sus RO (o las que siguen con alertas).
 * - Admin: solo las escaladas (rojas sin atender por mas de N horas).
 * - Comercial: solo ETA vencida o retrasada de sus RO, para informar al
 *   cliente si llama. Solo lectura: no marca vista ni pospone.
 */
final class AlertBoard
{
    /** Columnas de contenedor que necesitan las reglas. */
    public const CONTAINER_COLUMNS = 'containers:id,shipment_id,numero,tipo,fecha_retiro,fecha_devolucion';

    /** Lo que ve el Comercial. */
    private const COMERCIAL_TYPES = [Alert::ETA_OVERDUE, Alert::ETA_DELAYED];

    public function __construct(
        private readonly AlertDetector $detector,
        private readonly AlertThresholds $limits,
    ) {}

    public static function make(): self
    {
        $limits = AlertThresholds::fromSettings();

        return new self(new AlertDetector($limits), $limits);
    }

    public static function receivesAlerts(User $user): bool
    {
        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_CUSTOMER, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL]);
    }

    /**
     * Quien puede marcar vista o posponer: el Admin, el Customer u Operador
     * de la RO, o quien la sigue con alertas activadas.
     */
    public static function canAct(User $user, Shipment $shipment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasRole([User::ROLE_CUSTOMER, User::ROLE_OPERACIONES])) {
            return false;
        }

        return $shipment->customer_id === $user->id
            || $shipment->operador_id === $user->id
            || $shipment->userAssignments()->where('user_id', $user->id)->where('alerts_enabled', true)->exists();
    }

    /** RO activas cuyas alertas le tocan al usuario (con lo que necesitan las reglas). */
    public static function shipmentsFor(User $user): Collection
    {
        return Shipment::query()
            ->whereIn('estado_actual', Shipment::activeStates())
            ->when($user->isComercial(), fn ($query) => $query->where('comercial_id', $user->id))
            ->when(! $user->isAdmin() && ! $user->isComercial(), fn ($query) => $query->assignedForAlerts($user))
            ->with(['client:id,razon_social', self::CONTAINER_COLUMNS])
            ->get();
    }

    /**
     * Todas las alertas y plazos de una RO (tambien los lejanos, nivel "none"),
     * para la ficha.
     *
     * @return Collection<int, Alert>
     */
    public function forShipment(Shipment $shipment, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();
        $shipment->loadMissing(self::CONTAINER_COLUMNS);

        return self::sort($this->classify($this->detector->detect(collect([$shipment]), $now, includeUpcoming: true), $now));
    }

    /**
     * @param  Collection<int, Shipment>|null  $shipments  RO ya cargadas (para no repetir la consulta)
     * @return array{activas: Collection<int, Alert>, vistas: Collection<int, Alert>, pospuestas: Collection<int, Alert>}
     */
    public function for(User $user, ?Collection $shipments = null, ?CarbonImmutable $now = null): array
    {
        $groups = ['activas' => collect(), 'vistas' => collect(), 'pospuestas' => collect()];

        if (! self::receivesAlerts($user)) {
            return $groups;
        }

        $now ??= CarbonImmutable::now();
        $shipments ??= self::shipmentsFor($user);

        // Las RO que llegan ya vienen filtradas por vinculo (Inicio); el
        // Comercial solo mira las suyas.
        if ($user->isComercial()) {
            $shipments = $shipments->where('comercial_id', $user->id);
        }

        $alerts = $this->classify($this->detector->detect($shipments, $now), $now);

        if ($user->isComercial()) {
            $alerts = $alerts->filter(fn (Alert $alert) => in_array($alert->type, self::COMERCIAL_TYPES, true));
        }

        foreach ($alerts as $alert) {
            // El Admin no esta asignado a las RO: solo ve lo que se escalo.
            if ($user->isAdmin() && ! $alert->escalated) {
                continue;
            }

            $groups[match ($alert->status) {
                Alert::STATUS_SEEN => 'vistas',
                Alert::STATUS_SNOOZED => 'pospuestas',
                default => 'activas',
            }]->push($alert);
        }

        foreach ($groups as $name => $group) {
            $groups[$name] = self::sort($group);
        }

        return $groups;
    }

    /**
     * Cruza las alertas con las acciones guardadas (vista / pospuesta) y marca
     * las escaladas. Una alerta vista o pospuesta vuelve a ser activa si sube
     * de nivel; si cambia su plazo, cambia la huella y tampoco coincide.
     *
     * @param  Collection<int, Alert>  $alerts
     * @return Collection<int, Alert>
     */
    public function classify(Collection $alerts, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();

        $states = $alerts->isEmpty()
            ? collect()
            : ShipmentAlertState::query()
                ->with('user:id,name')
                ->whereIn('shipment_id', $alerts->map(fn (Alert $a) => $a->shipment->id)->unique()->values())
                ->get()
                ->keyBy(fn (ShipmentAlertState $state) => $state->key());

        return $alerts->each(function (Alert $alert) use ($states, $now) {
            $state = $states->get($alert->key());

            if ($state && ! AlertLevel::isHigher($alert->level, $state->nivel)) {
                if ($state->accion === ShipmentAlertState::ACTION_SNOOZED && $state->pospuesta_hasta?->isAfter($now)) {
                    $alert->status = Alert::STATUS_SNOOZED;
                    $alert->snoozedUntil = CarbonImmutable::instance($state->pospuesta_hasta);
                    $alert->stateId = $state->id;
                } elseif ($state->accion === ShipmentAlertState::ACTION_SEEN) {
                    $alert->status = Alert::STATUS_SEEN;
                    $alert->seenBy = $state->user;
                    $alert->seenAt = CarbonImmutable::instance($state->updated_at);
                    $alert->stateId = $state->id;
                }
            }

            $alert->escalated = $alert->level === AlertLevel::DANGER
                && $alert->status !== Alert::STATUS_SEEN
                && $now->gte($alert->criticalSince->addHours($this->limits->escalationHours));
        });
    }

    /** Rojas primero; dentro de cada nivel, lo que vence antes. */
    public static function sort(Collection $alerts): Collection
    {
        return $alerts->sort(function (Alert $a, Alert $b) {
            return [AlertLevel::RANK[$b->level], $a->sortMoment()->getTimestamp()]
                <=> [AlertLevel::RANK[$a->level], $b->sortMoment()->getTimestamp()];
        })->values();
    }

    /** Hasta cuando se puede posponer: nunca despues del vencimiento. */
    public static function snoozeUntil(Alert $alert, string $option, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now();

        // Lo ya vencido o sin plazo (ETA) solo se pospone 4 h.
        $until = $option === 'manana' && $alert->dueAt?->isAfter($now)
            ? $now->addDay()->setTime(8, 0)
            : $now->addHours(4);

        if ($alert->dueAt && $alert->dueAt->isAfter($now) && $until->isAfter($alert->dueAt)) {
            return $alert->dueAt;
        }

        return $until;
    }
}
