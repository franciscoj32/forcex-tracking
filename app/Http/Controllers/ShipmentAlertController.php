<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentAlertState;
use App\Models\ShipmentEvent;
use App\Services\Alerts\Alert;
use App\Services\Alerts\AlertBoard;
use App\Services\Alerts\AlertLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Acciones sobre las alertas criticas del Inicio: marcar como vista,
 * posponer o volver a activar. La alerta se recalcula antes de guardar,
 * para no actuar sobre un plazo que ya cambio.
 */
class ShipmentAlertController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shipment_id' => ['required', 'integer', Rule::exists('shipments', 'id')],
            'tipo' => ['required', Rule::in(array_keys(Alert::TYPE_LABELS))],
            'huella' => ['required', 'string', 'max:40'],
            'accion' => ['required', Rule::in([ShipmentAlertState::ACTION_SEEN, ShipmentAlertState::ACTION_SNOOZED])],
            'opcion' => ['nullable', Rule::in(['4h', 'manana'])],
        ]);

        $user = $request->user();
        $shipment = Shipment::with(AlertBoard::CONTAINER_COLUMNS)->findOrFail($data['shipment_id']);
        abort_unless(AlertBoard::canAct($user, $shipment), 403);

        $alert = AlertBoard::make()->forShipment($shipment)
            ->first(fn (Alert $a) => $a->type === $data['tipo']
                && $a->fingerprint === $data['huella']
                && $a->level !== AlertLevel::NONE);

        if (! $alert) {
            return back()->with('status', 'Esa alerta ya no está activa: el plazo cambió o se resolvió.');
        }

        $snoozedUntil = $data['accion'] === ShipmentAlertState::ACTION_SNOOZED
            ? AlertBoard::snoozeUntil($alert, $data['opcion'] ?? '4h')
            : null;

        ShipmentAlertState::updateOrCreate(
            ['shipment_id' => $shipment->id, 'tipo' => $alert->type, 'huella' => $alert->fingerprint],
            [
                'accion' => $data['accion'],
                'nivel' => $alert->level,
                'pospuesta_hasta' => $snoozedUntil,
                'user_id' => $user->id,
            ]
        );

        $description = $snoozedUntil
            ? "Alerta de {$alert->typeLabel()} pospuesta hasta el {$snoozedUntil->format('d/m H:i')}."
            : "Alerta de {$alert->typeLabel()} marcada como vista.";
        ShipmentEvent::recordAudit($shipment, $description, $user->id);

        return back()->with('status', $snoozedUntil
            ? "{$alert->typeLabel()} de {$shipment->routing_order} pospuesta hasta el {$snoozedUntil->format('d/m H:i')}."
            : "{$alert->typeLabel()} de {$shipment->routing_order} marcada como vista.");
    }

    public function destroy(Request $request, ShipmentAlertState $state): RedirectResponse
    {
        $shipment = $state->shipment;
        abort_unless($shipment && AlertBoard::canAct($request->user(), $shipment), 403);

        $label = Alert::TYPE_LABELS[$state->tipo] ?? $state->tipo;
        $state->delete();
        ShipmentEvent::recordAudit($shipment, "Alerta de {$label} reactivada.", $request->user()->id);

        return back()->with('status', "{$label} de {$shipment->routing_order} vuelve a las alertas activas.");
    }
}
