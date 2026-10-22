<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "Enviar por WhatsApp" (sin costo): deja constancia en el historial y abre
 * el WhatsApp del Customer con el mensaje listo (enlace wa.me). El Customer
 * es quien presiona Enviar desde su propio WhatsApp.
 */
class ShipmentWhatsAppController extends Controller
{
    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('communicateWithClient', $shipment);

        $contactIds = $shipment->client?->contacts()->pluck('id')->all() ?? [];

        $data = $request->validate([
            'contact_id' => ['nullable', 'integer', Rule::in($contactIds), 'required_without:telefono'],
            'telefono' => ['nullable', 'string', 'max:30', 'required_without:contact_id'],
            'mensaje' => ['required', 'string', 'max:2000'],
        ], [
            'contact_id.required_without' => 'Elige un contacto o escribe un número.',
            'telefono.required_without' => 'Elige un contacto o escribe un número.',
        ], ['mensaje' => 'mensaje']);

        $contact = filled($data['contact_id'] ?? null) ? $shipment->client->contacts()->find($data['contact_id']) : null;
        $phone = WhatsApp::normalizePhone($contact ? $contact->telefono : $data['telefono']);

        if ($phone === null) {
            return back()->withInput()->withErrors(['telefono' => 'Ese número no parece un celular con WhatsApp. Usa 9 dígitos (ej. 987654321) o el formato internacional (+51…).']);
        }

        ShipmentEvent::recordAudit($shipment, sprintf(
            'WhatsApp abierto para %s (%s): "%s"',
            $contact?->nombre ?? 'otro número',
            WhatsApp::display($phone),
            Str::limit(Str::squish($data['mensaje']), 120)
        ), $request->user()->id);

        return redirect()->away(WhatsApp::url($phone, trim($data['mensaje'])));
    }
}
