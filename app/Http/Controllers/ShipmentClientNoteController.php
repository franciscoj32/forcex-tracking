<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\ShipmentNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Notas del Customer para el cliente: aparecen en el tracking publico y,
 * si el Customer lo indica, se envian por correo a los contactos del cliente.
 */
class ShipmentClientNoteController extends Controller
{
    public function store(Request $request, Shipment $shipment, ShipmentNotifier $notifier): RedirectResponse
    {
        Gate::authorize('communicateWithClient', $shipment);

        $data = $request->validate([
            'nota' => ['required', 'string', 'min:5', 'max:2000'],
            'enviar_correo' => ['nullable', 'boolean'],
        ], [], ['nota' => 'nota para el cliente']);

        $sendEmail = $request->boolean('enviar_correo');

        DB::transaction(function () use ($shipment, $data, $sendEmail, $notifier, $request) {
            ShipmentEvent::recordClientNote($shipment, $data['nota'], $request->user()->id);

            if ($sendEmail) {
                $notifier->clientNote($shipment, trim($data['nota']), $request->user());
            }
        });

        return redirect()
            ->route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking'])
            ->with('status', $sendEmail
                ? 'Nota publicada en el tracking y enviada por correo al cliente.'
                : 'Nota publicada en el tracking del cliente.');
    }
}
