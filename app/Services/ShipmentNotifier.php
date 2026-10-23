<?php

namespace App\Services;

use App\Jobs\SendTrackingMail;
use App\Models\Client;
use App\Models\NotificationLog;
use App\Models\Quotation;
use App\Models\Shipment;
use App\Models\ShipmentDateChange;
use App\Models\User;
use App\Support\MailSubject;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Correos automaticos del circuito de carga.
 *
 *   A  RO registrada            -> Customer asignado + contactos del cliente
 *   B1 Cambio de ETD/ETA         -> Customer asignado (para validar)
 *   B2 Cambio aprobado           -> contactos del cliente (aviso de reprogramacion)
 *   C  Tarja / ingreso a almacen -> contactos del cliente + Customer
 *   P  Pre-alerta de llegada     -> cliente + agencia de aduana, transportista (los elige el Customer)
 *   Q  Cotizacion (PDF adjunto)  -> correos que elige el Comercial (Reply-To del Comercial)
 *   +  Nota del Customer, cliente pendiente de aprobacion, alertas diarias
 *
 * Los correos al cliente llevan Reply-To del Customer asignado: si el cliente
 * responde, le escribe a su unico punto de contacto.
 *
 * Cada envio queda en notification_logs y se procesa en la cola.
 */
class ShipmentNotifier
{
    public function enabled(): bool
    {
        return (bool) config('forcex.notifications', true);
    }

    /** Evento A: el Comercial registro la RO. */
    public function roRegistered(Shipment $shipment): void
    {
        $shipment->loadMissing(['client', 'comercial', 'customerService']);
        $customer = $shipment->customerService;

        $this->queue($shipment, 'ro_registrada', $this->emailsOf([$customer]), [
            'subject' => MailSubject::forShipment($shipment, 'Nueva RO asignada', internal: true),
            'heading' => 'Te asignaron una nueva RO',
            'intro' => ($shipment->comercial?->name ?? 'Un comercial').' registro esta RO y te asigno como Customer Service. Ya avisamos al cliente con su codigo de seguimiento.',
            'lines' => $this->shipmentLines($shipment, internal: true),
            'cta_label' => 'Abrir RO',
            'cta_url' => route('embarques.show', $shipment),
            'footer' => 'Asigna al operador desde la ficha de la RO.',
        ]);

        $this->queue($shipment, 'ro_registrada', $shipment->clientRecipientEmails(), [
            'subject' => MailSubject::forShipment($shipment, 'Registramos tu carga'),
            'heading' => 'Registramos tu orden de embarque',
            'intro' => 'Ya estamos coordinando tu carga. Puedes seguir su avance en linea con tu codigo de seguimiento.',
            'lines' => $this->shipmentLines($shipment),
            'cta_label' => 'Ver tracking',
            'cta_url' => $this->trackingUrl($shipment),
        ] + $this->replyToCustomer($customer));
    }

    /** Evento B1: Operaciones cambio ETD/ETA. El Customer debe validarlo. */
    public function dateChangePending(ShipmentDateChange $change): void
    {
        $shipment = $change->shipment()->with(['client', 'customerService'])->first();
        $recipients = $shipment->customerService ? [$shipment->customerService] : $this->admins();
        $delay = $change->delayInDays();

        $this->queue($shipment, 'cambio_fecha_pendiente', $this->emailsOf($recipients), [
            'subject' => MailSubject::forShipment(
                $shipment,
                "Cambio de {$change->fieldLabel()} ".($change->valor_publicado?->format('d/m') ?? 'sin fecha').' → '.($change->valor_nuevo?->format('d/m') ?? 'por confirmar'),
                internal: true,
                action: 'Validar',
            ),
            'heading' => "Operaciones actualizo el {$change->fieldLabel()}",
            'intro' => 'El cliente todavia ve la fecha anterior. Valida el cambio para publicarlo o descartalo si es una falsa alarma.',
            'lines' => [
                ['RO', $shipment->routing_order],
                ['Cliente', $shipment->client->razon_social],
                ['Fecha que ve el cliente', $change->valor_publicado?->format('d/m/Y') ?? 'Sin fecha'],
                ['Nueva fecha', $change->valor_nuevo?->format('d/m/Y') ?? 'Por confirmar'],
                ['Diferencia', $delay === null ? '-' : ($delay > 0 ? "+{$delay} dia(s)" : "{$delay} dia(s)")],
                ['Informado por', $change->changedBy->name],
            ],
            'cta_label' => 'Validar cambio',
            'cta_url' => route('embarques.show', $shipment),
        ], $change->cambiado_por_id);
    }

    /**
     * Evento B1 resumido: actualizacion masiva de ETD/ETA. Un solo correo por
     * Customer con todas sus RO del lote (si la RO no tiene Customer, a los Admin).
     *
     * @param  Collection<int, ShipmentDateChange>  $changes
     */
    public function dateChangesPendingBatch(Collection $changes, string $lote): void
    {
        $changes = \Illuminate\Database\Eloquent\Collection::make($changes->all())
            ->loadMissing(['shipment.client', 'shipment.customerService', 'changedBy']);
        $first = $changes->first();
        $byCustomer = $changes->groupBy(fn (ShipmentDateChange $change) => $change->shipment->customer_id ?? 0);

        foreach ($byCustomer as $group) {
            $customer = $group->first()->shipment->customerService;
            $recipients = $customer ? [$customer] : $this->admins();
            $field = $group->pluck('campo')->unique()->count() === 1 ? $group->first()->fieldLabel() : 'ETD/ETA';
            $vessel = trim(($first->shipment->nave ?? '').' '.($first->shipment->viaje ? 'viaje '.$first->shipment->viaje : ''));
            $newDates = $group->map(fn (ShipmentDateChange $change) => $change->valor_nuevo?->format('d/m'))->filter()->unique();

            $this->dispatch([], 'cambio_fecha_pendiente', $this->emailsOf($recipients), [
                'subject' => MailSubject::general(
                    "Cambio de {$field} en {$group->count()} RO",
                    [$vessel, $newDates->count() === 1 ? '→ '.$newDates->first() : null],
                    'Validar',
                ),
                'heading' => "Operaciones actualizó el {$field} de {$group->count()} RO",
                'intro' => 'Los clientes todavía ven la fecha anterior. Puedes validarlas todas juntas o revisarlas una por una.',
                'lines' => array_values(array_filter([
                    $vessel !== '' ? ['Nave / viaje', $vessel] : null,
                    ['Informado por', $first->changedBy->name],
                ])),
                'items' => $group->map(function (ShipmentDateChange $change) {
                    $delay = $change->delayInDays();

                    return "{$change->shipment->routing_order} · {$change->shipment->client?->razon_social}: {$change->fieldLabel()} "
                        .($change->valor_publicado?->format('d/m/Y') ?? 'sin fecha').' → '.($change->valor_nuevo?->format('d/m/Y') ?? 'por confirmar')
                        .($delay ? ' ('.($delay > 0 ? '+' : '').$delay.' día(s))' : '');
                })->values()->all(),
                'cta_label' => "Revisar los {$group->count()} cambios",
                'cta_url' => route('cambios-fecha.lote', $lote),
            ], $first->cambiado_por_id);
        }
    }

    /** Evento B2: el Customer valido el cambio. Aviso de reprogramacion al cliente. */
    public function dateChangeApproved(ShipmentDateChange $change, ?string $previousPublished): void
    {
        $shipment = $change->shipment()->with(['client', 'customerService'])->first();
        $label = $change->campo === 'fecha_eta' ? 'llegada' : 'salida';
        $isFirst = $previousPublished === null;

        $this->queue($shipment, 'reprogramacion', $shipment->clientRecipientEmails(), [
            'subject' => MailSubject::forShipment($shipment, $isFirst
                ? "{$change->fieldLabel()} confirmada ".($change->valor_nuevo?->format('d/m/Y') ?? '')
                : "{$change->fieldLabel()} reprogramada ".($previousPublished ? \Illuminate\Support\Str::beforeLast($previousPublished, '/') : 'sin fecha').' → '.($change->valor_nuevo?->format('d/m/Y') ?? 'por confirmar')),
            'heading' => $isFirst ? "Confirmamos la fecha estimada de {$label}" : "Cambio en la fecha estimada de {$label}",
            'intro' => $isFirst
                ? "Ya tenemos la fecha estimada de {$label} de tu carga."
                : "Te informamos que la fecha estimada de {$label} de tu carga fue actualizada.",
            'lines' => [
                ['RO', $shipment->routing_order],
                ['Fecha anterior', $previousPublished ?? 'Sin fecha'],
                ['Nueva fecha', $change->valor_nuevo?->format('d/m/Y') ?? 'Por confirmar'],
                ['Codigo de seguimiento', $shipment->tracking_code],
            ],
            'note' => $change->nota_cliente,
            'note_label' => 'Comentario de tu Customer Service',
            'cta_label' => 'Ver tracking',
            'cta_url' => $this->trackingUrl($shipment),
        ] + $this->replyToCustomer($shipment->customerService), $change->revisado_por_id);
    }

    /** Evento C: Operaciones registro la tarja o el ingreso a almacen. */
    public function cargoArrived(Shipment $shipment): void
    {
        $shipment->loadMissing(['client', 'customerService']);
        $lines = [
            ['RO', $shipment->routing_order],
            ['Almacen', $shipment->almacen_direccionamiento ?: 'Por confirmar'],
            ['Tarja', $shipment->fecha_tarja?->format('d/m/Y H:i') ?? '-'],
            ['Ingreso a almacen', $shipment->fecha_ingreso_almacen?->format('d/m/Y H:i') ?? '-'],
            ['Bultos tarjados', $shipment->bultos_tarjados ?? '-'],
        ];

        $this->queue($shipment, 'carga_arribo', $shipment->clientRecipientEmails(), [
            'subject' => MailSubject::forShipment($shipment, 'Carga en almacén'.($shipment->almacen_direccionamiento ? " {$shipment->almacen_direccionamiento}" : '')),
            'heading' => 'Tu carga arribo al Peru',
            'intro' => 'Tu carga ya se encuentra en el almacen de direccionamiento. Tu Customer Service coordinara los siguientes pasos.',
            'lines' => $lines,
            'cta_label' => 'Ver tracking',
            'cta_url' => $this->trackingUrl($shipment),
        ] + $this->replyToCustomer($shipment->customerService));

        $this->queue($shipment, 'carga_arribo', $this->emailsOf([$shipment->customerService]), [
            'subject' => MailSubject::forShipment($shipment, 'Tarja registrada'.($shipment->bultos_tarjados !== null ? " ({$shipment->bultos_tarjados} bultos)" : ''), internal: true),
            'heading' => 'Operaciones registro la tarja',
            'intro' => 'Ya avisamos al cliente que su carga arribo al Peru. Coordina retiro, aduanas y facturacion.',
            'lines' => array_merge([['Cliente', $shipment->client->razon_social]], $lines),
            'note' => $shipment->observacion_tarja,
            'note_label' => 'Observacion de tarja',
            'cta_label' => 'Abrir RO',
            'cta_url' => route('embarques.show', $shipment),
        ]);
    }

    /**
     * Pre-alerta de llegada (la envia el Customer desde la ficha).
     * - Cliente: aviso sencillo con la llegada estimada y su codigo de seguimiento.
     * - Cada proveedor y cada correo extra: su propio correo con los datos para
     *   el despacho anticipado y el retiro, y los documentos adjuntos.
     * Nadie ve los correos de los demas. Las respuestas le llegan al Customer.
     *
     * @param  Collection<int, \App\Models\Supplier>  $suppliers
     * @param  Collection<int, \App\Models\Document>  $documents
     */
    public function preAlert(Shipment $shipment, User $sender, array $clientEmails, Collection $suppliers, array $extraEmails, Collection $documents, ?string $note): void
    {
        $shipment->loadMissing(['client', 'customerService', 'containers']);
        $customer = $shipment->customerService ?? $sender;
        $eta = $shipment->fecha_eta;
        $vessel = trim(($shipment->nave ?? '').($shipment->viaje ? ' · viaje '.$shipment->viaje : '')) ?: null;

        if ($clientEmails !== []) {
            $this->queue($shipment, 'pre_alerta', $clientEmails, [
                'subject' => MailSubject::forShipment($shipment, 'Llegada estimada '.$eta->format('d/m')),
                'heading' => 'Tu carga está por llegar',
                'intro' => "Tu carga llega el {$eta->format('d/m/Y')} a {$shipment->puerto_descarga}. Ya estamos coordinando el despacho y el retiro.",
                'lines' => array_values(array_filter([
                    ['RO', $shipment->routing_order],
                    $shipment->bl ? [$shipment->isAirCargo() ? 'AWB' : 'BL', $shipment->bl] : null,
                    $vessel ? [$shipment->isAirCargo() ? 'Vuelo' : 'Nave', $vessel] : null,
                    ['Llegada estimada', $eta->format('d/m/Y')],
                    ['Codigo de seguimiento', $shipment->tracking_code],
                ])),
                'note' => $note,
                'note_label' => 'Comentario de tu Customer Service',
                'cta_label' => 'Ver tracking',
                'cta_url' => $this->trackingUrl($shipment),
            ] + $this->replyToCustomer($customer), $sender->id);
        }

        $containers = $shipment->containers->map(fn ($container) => trim("{$container->numero} ({$container->tipo})"))->join(', ');
        $operational = [
            'subject' => '[Pre-alerta] '.MailSubject::forShipment($shipment, 'Llegada '.$eta->format('d/m'), internal: true),
            'heading' => 'Pre-alerta de llegada',
            'intro' => 'Les informamos la llegada estimada de la siguiente carga para coordinar el despacho anticipado y el retiro.',
            'lines' => array_values(array_filter([
                ['RO', $shipment->routing_order],
                ['Cliente', $shipment->client->razon_social],
                $shipment->consignatario ? ['Consignatario', $shipment->consignatario] : null,
                $shipment->bl ? [$shipment->isAirCargo() ? 'AWB' : 'BL', $shipment->bl] : null,
                $vessel ? [$shipment->isAirCargo() ? 'Vuelo' : 'Nave', $vessel] : null,
                $shipment->naviera ? [$shipment->isAirCargo() ? 'Aerolinea' : 'Naviera', $shipment->naviera] : null,
                ['Ruta', "{$shipment->puerto_carga} -> {$shipment->puerto_descarga}"],
                ['Llegada estimada (ETA)', $eta->format('d/m/Y')],
                ['Tipo de carga', $shipment->isAirCargo() ? 'Aereo' : $shipment->tipo_carga],
                $containers !== '' ? [$shipment->containers->count() === 1 ? 'Contenedor' : 'Contenedores', $containers] : null,
                $shipment->peso !== null ? ['Peso', number_format((float) $shipment->peso, 2).' kg'] : null,
                $shipment->volumen !== null ? ['Volumen', number_format((float) $shipment->volumen, 2).' m3'] : null,
                $shipment->almacen_direccionamiento ? ['Almacen de direccionamiento', $shipment->almacen_direccionamiento] : null,
            ])),
            'items' => $documents->isNotEmpty() ? $documents->map(fn ($document) => "Adjunto: {$document->tipo_documento} ({$document->nombre_original})")->values()->all() : [],
            'attachments' => $documents->map(fn ($document) => [
                'path' => $document->ruta,
                'name' => $document->nombre_original,
                'mime' => $document->mime_type,
            ])->values()->all(),
            'note' => $note,
            'note_label' => 'Indicaciones',
            'footer' => "Para coordinar, responda este correo: le atenderá {$customer->name}.",
        ] + $this->replyToCustomer($customer);

        $externalEmails = $suppliers->pluck('correo')->filter()->merge($extraEmails)->map(fn ($email) => mb_strtolower(trim($email)))->unique();

        foreach ($externalEmails as $email) {
            $this->queue($shipment, 'pre_alerta', [$email], $operational, $sender->id);
        }
    }

    /**
     * Cotizacion enviada por correo con el PDF adjunto (guardado en el disco
     * privado). Si el cliente responde, le escribe al Comercial que cotizo.
     */
    public function quotationSent(Quotation $quotation, User $sender, array $emails, ?string $note, string $pdfPath): void
    {
        $quotation->loadMissing(['comercial', 'items']);
        $comercial = $quotation->comercial ?? $sender;
        $service = trim($quotation->cargoLabel().($quotation->incoterm ? " · {$quotation->incoterm}" : ''));

        $this->dispatch(['client_id' => $quotation->client_id], 'cotizacion', $emails, [
            'subject' => MailSubject::general("Cotización {$quotation->numero}", [trim($quotation->cargoLabel().' '.$quotation->routeLabel())]),
            'heading' => 'Te enviamos tu cotización',
            'intro' => "Adjuntamos en PDF la cotización {$quotation->numero} para {$quotation->razon_social}.",
            'lines' => array_values(array_filter([
                ['Cotización', $quotation->numero],
                ['Servicio', $service],
                $quotation->routeLabel() !== '' ? ['Ruta', $quotation->routeLabel()] : null,
                $quotation->mercancia ? ['Mercancía', $quotation->mercancia] : null,
                ['Total', Money::formatTotals($quotation->grandTotals())],
                ['Válida hasta', $quotation->validUntil()->format('d/m/Y')],
            ])),
            'attachments' => [[
                'path' => $pdfPath,
                'name' => $quotation->pdfFileName(),
                'mime' => 'application/pdf',
            ]],
            'note' => $note,
            'note_label' => $comercial->name,
            'footer' => "Para consultas o para confirmar, responde este correo: te atenderá {$comercial->name}.",
        ] + $this->replyToCustomer($comercial), $sender->id);
    }

    /** Nota del Customer enviada por correo al cliente. */
    public function clientNote(Shipment $shipment, string $note, User $author): void
    {
        $shipment->loadMissing(['client', 'customerService']);

        $this->queue($shipment, 'nota_cliente', $shipment->clientRecipientEmails(), [
            'subject' => MailSubject::forShipment($shipment, 'Novedades: '.$shipment->estado_actual),
            'heading' => 'Novedades de tu carga',
            'intro' => 'Tu Customer Service te envio una actualizacion sobre tu carga.',
            'lines' => [
                ['RO', $shipment->routing_order],
                ['Estado actual', $shipment->estado_actual],
            ],
            'note' => $note,
            'note_label' => $author->name,
            'cta_label' => 'Ver tracking',
            'cta_url' => $this->trackingUrl($shipment),
        ] + $this->replyToCustomer($shipment->customerService ?? $author), $author->id);
    }

    /** El Comercial registro un cliente: el Admin debe validarlo. */
    public function clientPendingApproval(Client $client): void
    {
        $client->loadMissing('registeredBy');

        $this->queueForClient($client, 'cliente_pendiente', $this->emailsOf($this->admins()), [
            'subject' => MailSubject::general('Cliente nuevo', [$client->razon_social, "RUC {$client->ruc}", 'Registrado por '.$client->registeredBy->name], 'Aprobar'),
            'heading' => 'Hay un cliente pendiente de aprobacion',
            'intro' => $client->registeredBy->name.' registro un cliente nuevo. No se le pueden emitir RO hasta que valides sus datos fiscales y su credito.',
            'lines' => [
                ['Razon social', $client->razon_social],
                ['RUC', $client->ruc],
                ['Credito propuesto', $client->creditLabel()],
                ['Linea propuesta', $client->linea_credito ? $client->moneda_linea_credito.' '.number_format((float) $client->linea_credito, 2) : '-'],
            ],
            'cta_label' => 'Revisar cliente',
            'cta_url' => route('clientes.show', $client),
        ]);
    }

    /** El Admin aprobo o rechazo el cliente: se avisa al Comercial. */
    public function clientReviewed(Client $client): void
    {
        $client->loadMissing('registeredBy');
        $approved = $client->isApproved();

        $this->queueForClient($client, 'cliente_revisado', $this->emailsOf([$client->registeredBy]), [
            'subject' => MailSubject::general($approved ? 'Cliente aprobado' : 'Cliente rechazado', [$client->razon_social, "RUC {$client->ruc}"], $approved ? null : 'Corregir'),
            'heading' => $approved ? 'Cliente aprobado' : 'Cliente rechazado',
            'intro' => $approved
                ? 'Ya puedes emitirle una RO a este cliente.'
                : 'Revisa el motivo, corrige la ficha y se enviara de nuevo a revision.',
            'lines' => [
                ['Razon social', $client->razon_social],
                ['Condicion de credito', $client->creditLabel()],
            ],
            'note' => $client->observacion_aprobacion,
            'note_label' => 'Observacion del Administrador',
            'cta_label' => 'Ver cliente',
            'cta_url' => route('clientes.show', $client),
        ]);
    }

    /** Resumen diario por usuario de las alertas criticas (ver SendDailyAlerts). */
    public function dailyDigest(User $user, array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->queue(null, 'alerta_diaria', $this->emailsOf([$user]), [
            'subject' => MailSubject::general('Alertas del '.now()->format('d/m'), [count($items).' embarque(s) requieren atención']),
            'heading' => 'Alertas del dia',
            'intro' => 'Estos embarques necesitan seguimiento hoy:',
            'items' => $items,
            'cta_label' => 'Abrir dashboard',
            'cta_url' => route('dashboard'),
            'footer' => 'Este resumen se envia automaticamente cada manana.',
        ]);
    }

    /* --------------------------------------------------------------------- */

    private function queue(?Shipment $shipment, string $event, array $recipients, array $payload, ?int $userId = null): void
    {
        if ($shipment) {
            // Todos los correos del embarque comparten hilo en el cliente de correo.
            $payload['thread_id'] = MailSubject::threadId($shipment);
        }

        $this->dispatch(['shipment_id' => $shipment?->id, 'client_id' => $shipment?->client_id], $event, $recipients, $payload, $userId);
    }

    private function queueForClient(Client $client, string $event, array $recipients, array $payload): void
    {
        $this->dispatch(['client_id' => $client->id], $event, $recipients, $payload, auth()->id());
    }

    private function dispatch(array $context, string $event, array $recipients, array $payload, ?int $userId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $recipients = array_values(array_unique(array_filter($recipients)));

        $log = NotificationLog::create($context + [
            'evento' => $event,
            'asunto' => $payload['subject'],
            'destinatarios' => $recipients,
            'responder_a' => $payload['reply_to'] ?? null,
            'estado' => $recipients === [] ? NotificationLog::STATUS_NO_RECIPIENTS : NotificationLog::STATUS_QUEUED,
            'user_id' => $userId ?? auth()->id(),
        ]);

        if ($recipients !== []) {
            SendTrackingMail::dispatch($log->id, $recipients, $payload);
        }
    }

    private function shipmentLines(Shipment $shipment, bool $internal = false): array
    {
        $lines = [
            ['RO', $shipment->routing_order],
            ['Tipo de carga', $shipment->tipo_carga],
            ['Ruta', "{$shipment->puerto_carga} ({$shipment->origen}) -> {$shipment->puerto_descarga} ({$shipment->destino_final})"],
            ['Incoterm', $shipment->incoterm ?: '-'],
            ['Codigo de seguimiento', $shipment->tracking_code],
        ];

        if ($internal) {
            array_splice($lines, 1, 0, [['Cliente', $shipment->client->razon_social], ['OT', $shipment->nro_orden_trabajo]]);
        }

        return $lines;
    }

    private function trackingUrl(Shipment $shipment): string
    {
        return route('tracking.public', ['code' => $shipment->tracking_code]);
    }

    private function replyToCustomer(?User $customer): array
    {
        return $customer ? ['reply_to' => $customer->email, 'reply_to_name' => $customer->name] : [];
    }

    /** @param iterable<?User> $users */
    private function emailsOf(iterable $users): array
    {
        return collect($users)
            ->filter(fn (?User $user) => $user && $user->isActive())
            ->pluck('email')
            ->filter()
            ->values()
            ->all();
    }

    private function admins(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('estado', User::ESTADO_ACTIVO)
            ->get();
    }
}
