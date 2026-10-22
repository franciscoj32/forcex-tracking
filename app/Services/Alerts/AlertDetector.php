<?php

namespace App\Services\Alerts;

use App\Models\Shipment;
use App\Models\ShipmentContainer;
use App\Models\ShipmentDateChange;
use App\Models\ShipmentEvent;
use App\Models\ShipmentPreAlert;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reglas de las alertas criticas. Recibe las RO activas (con 'containers'
 * cargados) y devuelve las alertas que deben mostrarse ahora.
 *
 * Una alerta "se resuelve" sola: cuando se registra el dato (retiro,
 * devolucion, ATD, arribo, cut-off cumplido) la regla deja de dispararse.
 */
final class AlertDetector
{
    /** Ver detect(): incluye los plazos lejanos (nivel "none"). */
    private bool $includeUpcoming = false;

    public function __construct(private readonly AlertThresholds $limits) {}

    public static function make(): self
    {
        return new self(AlertThresholds::fromSettings());
    }

    /**
     * Con $includeUpcoming tambien devuelve los plazos lejanos (nivel "none"),
     * para la cuenta regresiva de la ficha.
     *
     * @param  Collection<int, Shipment>  $shipments
     * @return Collection<int, Alert>
     */
    public function detect(Collection $shipments, ?CarbonImmutable $now = null, bool $includeUpcoming = false): Collection
    {
        $now ??= CarbonImmutable::now();
        $this->includeUpcoming = $includeUpcoming;
        $shipments = $shipments->filter(fn (Shipment $s) => $s->estado_actual !== 'Finalizado');

        $etaChangedAt = $this->lastEtaChanges($shipments);
        $arrivalDates = $this->arrivalDates($shipments);
        $preAlerts = $this->lastPreAlerts($shipments, $now);

        return $shipments->flatMap(fn (Shipment $s) => array_filter([
            $this->cutOff($s, $now),
            $this->etaOverdue($s, $now) ?? $this->etaDelayed($s, $now, $etaChangedAt),
            $this->demurrage($s, $now, $arrivalDates),
            $this->detention($s, $now),
            $this->storage($s, $now),
            $this->preArrival($s, $now, $preAlerts),
        ]))->values();
    }

    /**
     * Pre-alerta de llegada: desde N horas antes de la ETA (72 por defecto)
     * hasta el arribo. Amarilla al aparecer, roja a 24 h o con la ETA pasada.
     * Se resuelve al enviarla con la ETA vigente o al registrar el arribo.
     *
     * @param  array{last: array<int, ShipmentPreAlert>, sent: array<int, ShipmentPreAlert>}  $preAlerts
     */
    private function preArrival(Shipment $s, CarbonImmutable $now, array $preAlerts): ?Alert
    {
        if (! $s->fecha_eta || ! $s->isBeforeArrival()) {
            return null;
        }

        $eta = CarbonImmutable::instance($s->fecha_eta)->startOfDay();
        $last = $preAlerts['last'][$s->id] ?? null;

        if ($last?->isSent() && $last->eta_enviada?->isSameDay($eta)) {
            return null; // ya se envio con esta ETA
        }

        $hoursLeft = $now->diffInMinutes($eta, false) / 60;

        $level = match (true) {
            $hoursLeft <= $this->limits->criticalHours => AlertLevel::DANGER,
            $hoursLeft <= $this->limits->preAlertHours => AlertLevel::WARNING,
            $this->includeUpcoming => AlertLevel::NONE,
            default => null,
        };

        if ($level === null) {
            return null;
        }

        // Si ya se envio con otra ETA, se avisa que hay que reenviar con la nueva.
        $lastSent = $preAlerts['sent'][$s->id] ?? null;

        return Alert::deadline($s, Alert::PRE_ARRIVAL, $eta, $level, $eta->subHours($this->limits->criticalHours), [
            'eta' => $eta->format('d/m'),
            'retenida' => $last?->isHeld() ? $last->motivo_retencion : null,
            'retenida_por' => $last?->isHeld() ? $last->user->name : null,
            'enviada_eta' => $lastSent?->eta_enviada && ! $lastSent->eta_enviada->isSameDay($eta) ? $lastSent->eta_enviada->format('d/m') : null,
        ]);
    }

    /**
     * Ultima accion de pre-alerta por RO (y ultimo envio, si la ultima accion
     * fue una retencion). Solo para las RO cerca de su ETA: una consulta.
     *
     * @return array{last: array<int, ShipmentPreAlert>, sent: array<int, ShipmentPreAlert>}
     */
    private function lastPreAlerts(Collection $shipments, CarbonImmutable $now): array
    {
        $horizon = $now->addHours($this->includeUpcoming ? 24 * 365 : $this->limits->preAlertHours);

        $ids = $shipments
            ->filter(fn (Shipment $s) => $s->fecha_eta && $s->isBeforeArrival() && $s->fecha_eta->lte($horizon))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return ['last' => [], 'sent' => []];
        }

        // Ordenadas por id: keyBy deja la ultima de cada RO.
        $rows = ShipmentPreAlert::query()
            ->with('user:id,name')
            ->whereIn('shipment_id', $ids)
            ->orderBy('id')
            ->get(['id', 'shipment_id', 'estado', 'eta_enviada', 'motivo_retencion', 'user_id']);

        return [
            'last' => $rows->keyBy('shipment_id')->all(),
            'sent' => $rows->filter(fn (ShipmentPreAlert $row) => $row->isSent())->keyBy('shipment_id')->all(),
        ];
    }

    private function cutOff(Shipment $s, CarbonImmutable $now): ?Alert
    {
        if (! $s->fecha_cut_off || $s->cut_off_cumplido_en || ! $s->isBeforeDeparture()) {
            return null;
        }

        return $this->withDeadline($s, Alert::CUT_OFF, CarbonImmutable::instance($s->fecha_cut_off), $now);
    }

    private function etaOverdue(Shipment $s, CarbonImmutable $now): ?Alert
    {
        if (! $s->fecha_eta || ! $s->isBeforeArrival()) {
            return null;
        }

        $eta = CarbonImmutable::instance($s->fecha_eta)->startOfDay();
        if (! $eta->lt($now->startOfDay())) {
            return null;
        }

        return Alert::event($s, Alert::ETA_OVERDUE,
            criticalSince: $eta->addDay(),
            fingerprint: $eta->format('Ymd'),
            data: ['eta' => $eta->format('d/m/Y'), 'dias_vencida' => (int) $eta->diffInDays($now->startOfDay())]);
    }

    /** @param  array<int, CarbonImmutable>  $etaChangedAt */
    private function etaDelayed(Shipment $s, CarbonImmutable $now, array $etaChangedAt): ?Alert
    {
        if (! $s->fecha_eta || ! $s->fecha_eta_original || ! $s->isBeforeArrival()) {
            return null;
        }

        $original = CarbonImmutable::instance($s->fecha_eta_original)->startOfDay();
        $eta = CarbonImmutable::instance($s->fecha_eta)->startOfDay();
        $delay = (int) $original->diffInDays($eta, false); // positivo = se atraso

        if ($delay < $this->limits->etaDelayDays) {
            return null;
        }

        return Alert::event($s, Alert::ETA_DELAYED,
            criticalSince: $etaChangedAt[$s->id] ?? CarbonImmutable::instance($s->updated_at ?? $now),
            fingerprint: $eta->format('Ymd'),
            data: ['original' => $original->format('d/m'), 'nueva' => $eta->format('d/m'), 'dias' => $delay]);
    }

    /** @param  array<int, CarbonImmutable>  $arrivalDates */
    private function demurrage(Shipment $s, CarbonImmutable $now, array $arrivalDates): ?Alert
    {
        if ($s->tipo_carga !== 'FCL' || $s->dias_libres_demurrage === null) {
            return null;
        }

        // Desde la descarga; si falta, desde la fecha del hito "Arribo a puerto".
        $start = $s->fecha_descarga ? CarbonImmutable::instance($s->fecha_descarga) : ($arrivalDates[$s->id] ?? null);
        if (! $start) {
            return null;
        }

        $notPickedUp = $s->containers->filter(fn (ShipmentContainer $c) => ! $c->fecha_retiro);
        if ($s->containers->isNotEmpty() && $notPickedUp->isEmpty()) {
            return null; // todos retirados: el demurrage se detuvo
        }

        return $this->withDeadline($s, Alert::DEMURRAGE, FreeTime::endsAt($start, $s->dias_libres_demurrage), $now, [
            'dia' => FreeTime::dayNumber($start, $now),
            'libres' => $s->dias_libres_demurrage,
            'pendientes' => $notPickedUp->count(),
            'total' => $s->containers->count(),
        ]);
    }

    private function detention(Shipment $s, CarbonImmutable $now): ?Alert
    {
        if ($s->tipo_carga !== 'FCL' || $s->dias_libres_detention === null) {
            return null;
        }

        $out = $s->containers->filter(fn (ShipmentContainer $c) => $c->fecha_retiro && ! $c->fecha_devolucion);
        if ($out->isEmpty()) {
            return null;
        }

        // Peor caso: el contenedor que vence primero.
        $dueAt = $out
            ->map(fn (ShipmentContainer $c) => FreeTime::endsAt($c->fecha_retiro, $s->dias_libres_detention))
            ->sort()
            ->first();

        return $this->withDeadline($s, Alert::DETENTION, $dueAt, $now, [
            'contenedores' => $out->pluck('numero')->values()->all(),
        ]);
    }

    private function storage(Shipment $s, CarbonImmutable $now): ?Alert
    {
        if ($s->dias_libres_almacen === null || ! $s->fecha_ingreso_almacen || $s->hasLeftWarehouse()) {
            return null;
        }

        $start = CarbonImmutable::instance($s->fecha_ingreso_almacen);

        return $this->withDeadline($s, Alert::STORAGE, FreeTime::endsAt($start, $s->dias_libres_almacen), $now, [
            'almacen' => $s->almacen_direccionamiento,
            'dia' => FreeTime::dayNumber($start, $now),
            'libres' => $s->dias_libres_almacen,
        ]);
    }

    private function withDeadline(Shipment $s, string $type, CarbonImmutable $dueAt, CarbonImmutable $now, array $data = []): ?Alert
    {
        $level = AlertLevel::for($dueAt, $now, $this->limits);

        if ($level === null) {
            if (! $this->includeUpcoming) {
                return null; // falta mas del margen preventivo
            }

            $level = AlertLevel::NONE;
        }

        return Alert::deadline($s, $type, $dueAt, $level, $dueAt->subHours($this->limits->criticalHours), $data);
    }

    /**
     * Cuando cambio la ETA por ultima vez (desde ahi corre el escalamiento de
     * "ETA retrasada"). Una sola consulta, y solo si hay RO con retraso.
     *
     * @return array<int, CarbonImmutable>
     */
    private function lastEtaChanges(Collection $shipments): array
    {
        $ids = $shipments
            ->filter(fn (Shipment $s) => $s->fecha_eta && $s->fecha_eta_original && $s->fecha_eta->gt($s->fecha_eta_original))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        return ShipmentDateChange::query()
            ->whereIn('shipment_id', $ids)
            ->where('campo', 'fecha_eta')
            ->selectRaw('shipment_id, MAX(created_at) as changed_at')
            ->groupBy('shipment_id')
            ->pluck('changed_at', 'shipment_id')
            ->map(fn ($value) => CarbonImmutable::parse($value))
            ->all();
    }

    /**
     * Fecha del hito "Arribo a puerto" para las FCL con dias libres pero sin
     * fecha de descarga. Una sola consulta, y solo si hace falta.
     *
     * @return array<int, CarbonImmutable>
     */
    private function arrivalDates(Collection $shipments): array
    {
        $ids = $shipments
            ->filter(fn (Shipment $s) => $s->tipo_carga === 'FCL'
                && $s->dias_libres_demurrage !== null
                && ! $s->fecha_descarga
                && $s->hasArrived())
            ->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        return ShipmentEvent::query()
            ->whereIn('shipment_id', $ids)
            ->where('tipo', ShipmentEvent::TYPE_STATE)
            ->where('estado', 'Arribo a puerto')
            ->selectRaw('shipment_id, MIN(fecha) as arrived_at')
            ->groupBy('shipment_id')
            ->pluck('arrived_at', 'shipment_id')
            ->map(fn ($value) => CarbonImmutable::parse($value))
            ->all();
    }
}
