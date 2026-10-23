<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentPreAlert;
use App\Services\PreAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pre-alerta de llegada. El sistema la pide 72 h antes de la ETA y el Customer
 * decide: la envia a los externos (cliente, agencia de aduana, transportista) o
 * la retiene, por ejemplo hasta que el cliente pague.
 *
 * Se trabaja en una ventana emergente: en la ficha (ya cargada en la pagina) y
 * en el Inicio (se pide con ?modal=1 al hacer clic). La pagina completa queda
 * para enlaces directos y para mostrar errores cuando se envio desde el Inicio.
 *
 * "volver" indica de donde vino: inicio, ficha o pagina.
 */
class ShipmentPreAlertController extends Controller
{
    private const ORIGINS = ['inicio', 'ficha', 'pagina'];

    public function __construct(private readonly PreAlertService $preAlerts) {}

    public function create(Request $request, Shipment $shipment): View
    {
        Gate::authorize('sendPreAlert', $shipment);

        $data = $this->preAlerts->formData($shipment);

        // Contenido de la ventana emergente del Inicio (sin el menu ni la cabecera).
        if ($request->boolean('modal')) {
            return view('shipments.partials.pre-alert-form', $data + ['volver' => $this->origin($request, 'inicio')]);
        }

        return view('shipments.pre-alert', $data + ['volver' => 'pagina']);
    }

    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('sendPreAlert', $shipment);
        $origin = $this->origin($request);

        if ($reason = $this->preAlerts->blockReason($shipment)) {
            return $this->failed($shipment, $origin, ['pre_alerta' => $reason]);
        }

        $supplierIds = $this->preAlerts->supplierOptions($shipment)
            ->filter(fn (array $option) => filled($option['supplier']->correo))
            ->map(fn (array $option) => $option['supplier']->id)
            ->all();
        $documentIds = $this->preAlerts->documentOptions($shipment)->map(fn (array $option) => $option['document']->id)->all();

        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return $this->failed($shipment, $origin, $validator->errors());
        }

        $data = $validator->validated();
        $data = [
            'enviar_cliente' => $request->boolean('enviar_cliente'),
            'supplier_ids' => array_map('intval', $data['supplier_ids'] ?? []),
            'correos_extra' => collect($data['correos_extra'] ?? [])->map(fn ($email) => trim((string) $email))->filter()->unique()->values()->all(),
            'document_ids' => array_map('intval', $data['document_ids'] ?? []),
            'nota' => filled($data['nota'] ?? null) ? trim($data['nota']) : null,
        ];

        $hasClient = $data['enviar_cliente'] && $shipment->clientRecipientEmails() !== [];

        if (! $hasClient && $data['supplier_ids'] === [] && $data['correos_extra'] === []) {
            return $this->failed($shipment, $origin, ['destinatarios' => 'Elige al menos un destinatario.']);
        }

        $documents = $shipment->documents()->whereIn('id', $data['document_ids'])->get();

        if ($this->preAlerts->attachmentsSize($documents) > ShipmentPreAlert::MAX_ATTACHMENT_BYTES) {
            return $this->failed($shipment, $origin, [
                'document_ids' => 'Los adjuntos superan '.intdiv(ShipmentPreAlert::MAX_ATTACHMENT_BYTES, 1024 * 1024).' MB. Quita alguno para que el correo no rebote.',
            ]);
        }

        $preAlert = $this->preAlerts->send($shipment, $request->user(), $data);

        return $this->done($shipment, $origin, "Pre-alerta de {$shipment->routing_order} enviada a ".$preAlert->recipientsSummary().'.');
    }

    public function hold(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('sendPreAlert', $shipment);
        $origin = $this->origin($request);

        $validator = Validator::make($request->all(), [
            'motivo_retencion' => ['required', 'string', 'max:500'],
        ], [
            'motivo_retencion.required' => 'Escribe por qué la retienes (por ejemplo, facturas pendientes de pago).',
        ]);

        if ($validator->fails()) {
            return $this->failed($shipment, $origin, $validator->errors());
        }

        $this->preAlerts->hold($shipment, $request->user(), trim($validator->validated()['motivo_retencion']));

        return $this->done($shipment, $origin, "Pre-alerta de {$shipment->routing_order} retenida. Sigue visible hasta que la envíes.");
    }

    private function origin(Request $request, string $default = 'pagina'): string
    {
        $origin = $request->input('volver', $default);

        return in_array($origin, self::ORIGINS, true) ? $origin : $default;
    }

    /**
     * Con errores: la ficha vuelve a abrir su ventana con lo escrito; desde el
     * Inicio (ventana cargada aparte) se muestra la pagina completa con los errores.
     */
    private function failed(Shipment $shipment, string $origin, MessageBag|array $errors): RedirectResponse
    {
        $redirect = $origin === 'ficha'
            ? redirect()->route('embarques.show', $shipment)
            : redirect()->route('embarques.pre-alerta', $shipment);

        return $redirect->withErrors($errors)->withInput();
    }

    private function done(Shipment $shipment, string $origin, string $message): RedirectResponse
    {
        $redirect = $origin === 'inicio'
            ? redirect()->route('dashboard')
            : redirect()->route('embarques.show', $shipment);

        return $redirect->with('status', $message);
    }
}
