<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Provision;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Money;
use App\Services\Alerts\AlertBoard;
use App\Support\ShipmentAttention;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Bandeja "Requiere tu accion" del Inicio.
 *
 * Junta en una sola lista, ordenada por urgencia, lo que cada rol tiene que
 * resolver hoy. Los pendientes de embarques salen de ShipmentAttention (los
 * mismos que ve la ficha), filtrados por lo que le corresponde a cada rol.
 *
 * Nivel: danger (vencido), warning (por hacer), info (para tener presente).
 */
class ActionInbox
{
    /**
     * Que tareas de embarque le tocan a cada rol. Los plazos (ETA vencida,
     * cut-off, demurrage...) no son tareas: van al bloque de Alertas
     * (App\Services\Alerts\AlertBoard).
     */
    private const SHIPMENT_KEYS = [
        User::ROLE_ADMIN => ['cambio-fecha', 'operador'],
        User::ROLE_COMERCIAL => [],
        User::ROLE_CUSTOMER => ['cambio-fecha', 'operador', 'documentos', 'documentos-validar', 'documento-observado'],
        User::ROLE_OPERACIONES => ['contenedores', 'tarja', 'documentos', 'documento-observado'],
    ];

    private const LEVEL_ORDER = ['danger' => 0, 'warning' => 1, 'info' => 2];

    /**
     * RO activas vinculadas al usuario, con todo lo que necesitan las tareas y
     * las alertas. El Inicio las carga una sola vez y las comparte.
     */
    public function shipmentsFor(User $user): Collection
    {
        $keys = self::SHIPMENT_KEYS[$user->role] ?? [];
        $needsDocuments = array_intersect($keys, ['documentos', 'documentos-validar', 'documento-observado']) !== [];

        return Shipment::query()
            ->whereIn('estado_actual', Shipment::activeStates())
            ->when(! $user->isAdmin(), fn ($query) => $query->linkedTo($user))
            ->with(array_filter([
                'client:id,razon_social',
                AlertBoard::CONTAINER_COLUMNS,
                // Los documentos solo los usan las tareas de Customer y Operaciones.
                $needsDocuments ? 'documents:id,shipment_id,categoria,tipo_documento,estado_validacion,observacion_validacion,created_at' : null,
                'pendingDateChanges.changedBy:id,name',
            ]))
            ->get();
    }

    /**
     * @param  Collection<int, Shipment>|null  $shipments  RO ya cargadas con shipmentsFor()
     * @return Collection<int, array{level:string, reference:string, subject:?string, text:string, action_label:?string, action_url:?string, url:?string}>
     */
    public function for(User $user, ?Collection $shipments = null): Collection
    {
        $items = collect()
            ->merge($this->shipmentItems($user, $shipments))
            ->merge($this->clientItems($user))
            ->merge($this->costItems($user));
        // La llegada proxima ya no es una tarea: la pide la alerta "Pre-alerta de llegada".

        return $items
            ->sortBy(fn (array $item) => self::LEVEL_ORDER[$item['level']] ?? 9)
            ->values();
    }

    private function shipmentItems(User $user, ?Collection $shipments): Collection
    {
        $keys = self::SHIPMENT_KEYS[$user->role] ?? [];

        if ($keys === []) {
            return collect();
        }

        $shipments ??= $this->shipmentsFor($user);

        $items = $shipments->flatMap(function (Shipment $shipment) use ($user, $keys) {
            // Solo los pendientes del rol: el Admin no arma la lista de documentos de cada RO.
            return collect(ShipmentAttention::for($shipment, $user, $keys))
                ->map(fn (array $item) => [
                    'level' => $item['level'],
                    'reference' => "{$shipment->nro_orden_trabajo} · {$shipment->routing_order}",
                    'subject' => $shipment->client?->razon_social,
                    'text' => $item['text'],
                    'action_label' => $item['action_label'],
                    'action_url' => $item['action_url'],
                    'url' => route('embarques.show', $shipment),
                    'lote' => $item['lote'] ?? null,
                    'change' => $item['change'] ?? null,
                    'shipment' => $shipment,
                ]);
        });

        // Cambios de fecha de una misma actualizacion masiva: un solo pendiente.
        [$batched, $single] = $items->partition(fn (array $item) => $item['lote'] !== null);

        return $single
            ->merge($batched->groupBy('lote')->map(fn (Collection $group, string $lote) => $group->count() === 1
                ? $group->first()
                : $this->batchItem($group, $lote)))
            ->map(fn (array $item) => Arr::except($item, ['lote', 'change', 'shipment']))
            ->values();
    }

    /** "Cambio de ETA en 5 RO · MSC ANNA viaje 442W → 02/11". */
    private function batchItem(Collection $group, string $lote): array
    {
        $first = $group->first();
        $shipment = $first['shipment'];
        $fields = $group->map(fn (array $item) => $item['change']->fieldLabel())->unique();
        $dates = $group->map(fn (array $item) => $item['change']->valor_nuevo?->format('d/m/Y'))->filter()->unique();
        $vessel = $shipment->nave ? trim($shipment->nave.($shipment->viaje ? " viaje {$shipment->viaje}" : '')) : null;
        $canReview = $group->contains(fn (array $item) => $item['action_url'] !== null);

        return [
            'level' => 'warning',
            'reference' => "Cambio de {$fields->join('/')} en {$group->count()} RO",
            'subject' => $vessel,
            'text' => $first['change']->changedBy->name." cambió la {$fields->join('/')}"
                .($dates->count() === 1 ? " a {$dates->first()}" : '')
                .' de '.$group->map(fn (array $item) => $item['shipment']->routing_order)->join(', ')
                .'. Los clientes aún ven la fecha anterior.',
            'action_label' => $canReview ? 'Revisar todas' : null,
            'action_url' => $canReview ? route('cambios-fecha.lote', $lote) : null,
            'url' => route('cambios-fecha.lote', $lote),
            'lote' => $lote,
            'change' => null,
            'shipment' => null,
        ];
    }

    private function clientItems(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Client::query()->pendingApproval()->with('registeredBy:id,name')->oldest()->get()
                ->map(fn (Client $client) => [
                    'level' => 'warning',
                    'reference' => $client->razon_social,
                    'subject' => "RUC {$client->ruc}",
                    'text' => 'Cliente nuevo por aprobar, registrado por '.$client->registeredBy?->name.' el '.$client->created_at->format('d/m').'.',
                    'action_label' => 'Revisar',
                    'action_url' => route('clientes.show', $client),
                    'url' => route('clientes.show', $client),
                ]);
        }

        if ($user->isComercial()) {
            return Client::query()
                ->where('registrado_por_id', $user->id)
                ->where('estado_aprobacion', '!=', Client::APPROVAL_APPROVED)
                ->latest()
                ->get()
                ->map(fn (Client $client) => [
                    'level' => $client->isRejected() ? 'warning' : 'info',
                    'reference' => $client->razon_social,
                    'subject' => "RUC {$client->ruc}",
                    'text' => $client->isRejected()
                        ? 'El Administrador rechazó este cliente. Corrige la ficha para enviarla de nuevo.'
                        : 'Esperando aprobación del Administrador. Aún no puedes emitirle una RO.',
                    'action_label' => $client->isRejected() ? 'Corregir' : null,
                    'action_url' => $client->isRejected() ? route('clientes.edit', $client) : null,
                    'url' => route('clientes.show', $client),
                ]);
        }

        return collect();
    }

    private function costItems(User $user): Collection
    {
        if (! $user->isOperaciones()) {
            return collect();
        }

        $pending = Provision::query()
            ->where('estado', Provision::STATUS_PENDING)
            ->whereHas('shipment', fn ($query) => $query->where('operador_id', $user->id))
            ->get(['id', 'monto', 'moneda']);

        if ($pending->isEmpty()) {
            return collect();
        }

        $totals = $pending->groupBy('moneda')->map(fn ($rows) => (float) $rows->sum('monto'))->all();

        return collect([[
            'level' => 'warning',
            'reference' => 'Costos de proveedores',
            'subject' => null,
            'text' => $pending->count().' '.($pending->count() === 1 ? 'costo pendiente' : 'costos pendientes').' de pago: '.Money::formatTotals($totals).'.',
            'action_label' => 'Ver provisiones',
            'action_url' => route('provisiones.index'),
            'url' => null,
        ]]);
    }
}
