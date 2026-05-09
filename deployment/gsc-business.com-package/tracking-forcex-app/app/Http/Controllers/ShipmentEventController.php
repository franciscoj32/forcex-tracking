<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShipmentEventController extends Controller
{
    public function overview(): View
    {
        $query = Shipment::with(['client', 'events' => fn ($builder) => $builder->latest('fecha')->take(1)])
            ->latest('updated_at');

        if (auth()->user()->role === User::ROLE_CLIENTE) {
            $query->where('client_id', auth()->user()->client_id);
        }

        return view('tracking.index', [
            'shipments' => $query->paginate(12),
        ]);
    }

    public function index(Shipment $shipment): RedirectResponse
    {
        $this->authorizeAccess($shipment);

        return redirect()->route('embarques.show', [
            'shipment' => $shipment,
            'tab' => 'tracking',
        ]);
    }

    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

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

        ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'estado' => $data['estado'],
            'fecha' => $data['fecha'],
            'descripcion' => $data['descripcion'] ?? null,
            'user_id' => auth()->id(),
        ]);

        $shipment->update(['estado_actual' => $data['estado']]);

        return redirect()->route('embarques.show', [
            'shipment' => $shipment,
            'tab' => 'tracking',
        ])->with('status', 'Evento de tracking registrado correctamente.');
    }

    private function authorizeAccess(Shipment $shipment): void
    {
        $user = auth()->user();

        if ($user->role === User::ROLE_CLIENTE && $shipment->client_id !== $user->client_id) {
            abort(403);
        }
    }
}
