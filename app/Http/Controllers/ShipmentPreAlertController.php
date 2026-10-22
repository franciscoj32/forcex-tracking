<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentPreAlert;
use App\Services\PreAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pre-alerta de llegada. El sistema la pide 72 h antes de la ETA (alerta del
 * Inicio) y el Customer decide: la envia a los externos (cliente, agencia de
 * aduana, transportista) o la retiene, por ejemplo hasta que el cliente pague.
 */
class ShipmentPreAlertController extends Controller
{
    public function __construct(private readonly PreAlertService $preAlerts) {}

    public function create(Shipment $shipment): View
    {
        Gate::authorize('sendPreAlert', $shipment);

        $shipment->load(['client', 'customerService:id,name,email', 'containers']);
        $suppliers = $this->preAlerts->supplierOptions($shipment);

        return view('shipments.pre-alert', [
            'shipment' => $shipment,
            'blockReason' => $this->preAlerts->blockReason($shipment),
            'pendingEtaChange' => $this->preAlerts->pendingEtaChange($shipment),
            'clientEmails' => $shipment->clientRecipientEmails(),
            'suppliers' => $suppliers,
            'missingSuppliers' => $this->preAlerts->missingKeySuppliers($suppliers),
            'documents' => $this->preAlerts->documentOptions($shipment),
            'credit' => $this->preAlerts->creditSummary($shipment),
            'history' => $shipment->preAlerts()->with('user:id,name')->limit(10)->get(),
            'maxAttachmentMb' => intdiv(ShipmentPreAlert::MAX_ATTACHMENT_BYTES, 1024 * 1024),
        ]);
    }

    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('sendPreAlert', $shipment);

        if ($reason = $this->preAlerts->blockReason($shipment)) {
            return back()->withErrors(['pre_alerta' => $reason]);
        }

        $supplierIds = $this->preAlerts->supplierOptions($shipment)
            ->filter(fn (array $option) => filled($option['supplier']->correo))
            ->map(fn (array $option) => $option['supplier']->id)
            ->all();
        $documentIds = $this->preAlerts->documentOptions($shipment)->map(fn (array $option) => $option['document']->id)->all();

        $data = $request->validate([
            'enviar_cliente' => ['nullable', 'boolean'],
            'supplier_ids' => ['nullable', 'array'],
            'supplier_ids.*' => ['integer', Rule::in($supplierIds)],
            'correos_extra' => ['nullable', 'array', 'max:'.ShipmentPreAlert::MAX_EXTRA_EMAILS],
            'correos_extra.*' => ['nullable', 'email:rfc', 'max:150'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['integer', Rule::in($documentIds)],
            'nota' => ['nullable', 'string', 'max:1000'],
        ], [
            'correos_extra.max' => 'Puedes agregar hasta '.ShipmentPreAlert::MAX_EXTRA_EMAILS.' correos adicionales.',
            'correos_extra.*.email' => 'El correo ":input" no es válido.',
        ], ['nota' => 'nota']);

        $data = [
            'enviar_cliente' => $request->boolean('enviar_cliente'),
            'supplier_ids' => array_map('intval', $data['supplier_ids'] ?? []),
            'correos_extra' => collect($data['correos_extra'] ?? [])->map(fn ($email) => trim((string) $email))->filter()->unique()->values()->all(),
            'document_ids' => array_map('intval', $data['document_ids'] ?? []),
            'nota' => filled($data['nota'] ?? null) ? trim($data['nota']) : null,
        ];

        $hasClient = $data['enviar_cliente'] && $shipment->clientRecipientEmails() !== [];

        if (! $hasClient && $data['supplier_ids'] === [] && $data['correos_extra'] === []) {
            return back()->withInput()->withErrors(['destinatarios' => 'Elige al menos un destinatario.']);
        }

        $documents = $shipment->documents()->whereIn('id', $data['document_ids'])->get();

        if ($this->preAlerts->attachmentsSize($documents) > ShipmentPreAlert::MAX_ATTACHMENT_BYTES) {
            return back()->withInput()->withErrors([
                'document_ids' => 'Los adjuntos superan '.intdiv(ShipmentPreAlert::MAX_ATTACHMENT_BYTES, 1024 * 1024).' MB. Quita alguno para que el correo no rebote.',
            ]);
        }

        $preAlert = $this->preAlerts->send($shipment, $request->user(), $data);

        return redirect()
            ->route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking'])
            ->with('status', 'Pre-alerta enviada a '.$preAlert->recipientsSummary().'.');
    }

    public function hold(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('sendPreAlert', $shipment);

        $data = $request->validate([
            'motivo_retencion' => ['required', 'string', 'max:500'],
        ], [
            'motivo_retencion.required' => 'Escribe por qué la retienes (por ejemplo, facturas pendientes de pago).',
        ]);

        $this->preAlerts->hold($shipment, $request->user(), trim($data['motivo_retencion']));

        return redirect()
            ->route('embarques.pre-alerta', $shipment)
            ->with('status', 'Pre-alerta retenida. Sigue en tus alertas hasta que la envíes.');
    }
}
