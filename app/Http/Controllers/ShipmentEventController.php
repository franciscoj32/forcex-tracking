<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ShipmentEventController extends Controller
{
    /** El seguimiento se ve en la pestana Seguimiento de cada embarque. */
    public function overview(): RedirectResponse
    {
        return redirect()->route('embarques.index');
    }

    public function index(Shipment $shipment): RedirectResponse
    {
        Gate::authorize('view', $shipment);

        return redirect()->route('embarques.show', [
            'shipment' => $shipment,
            'tab' => 'tracking',
        ]);
    }

    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('registerTracking', $shipment);

        $data = $request->validate([
            'estado' => [
                'required',
                Rule::in(Shipment::TRACKING_STATES),
                function (string $attribute, mixed $value, \Closure $fail) use ($shipment) {
                    if (! is_string($value) || ! $shipment->canAdvanceToTrackingState($value)) {
                        $fail('El nuevo estado debe avanzar el flujo del embarque y no puede repetir ni retroceder el estado actual.');
                    }
                },
            ],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string'],
        ]);

        // El cliente ve el hito con su texto estandar; la descripcion es interna.
        ShipmentEvent::recordState($shipment, $data['estado'], $data['fecha'], $data['descripcion'] ?? null, auth()->id());

        $shipment->update(['estado_actual' => $data['estado']]);

        return redirect()->route('embarques.show', [
            'shipment' => $shipment,
            'tab' => 'tracking',
        ])->with('status', 'Evento de tracking registrado correctamente.');
    }
}
