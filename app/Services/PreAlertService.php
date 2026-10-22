<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Provision;
use App\Models\Shipment;
use App\Models\ShipmentDateChange;
use App\Models\ShipmentEvent;
use App\Models\ShipmentPreAlert;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pre-alerta de llegada: lo que el Customer revisa antes de avisar a los
 * externos (destinatarios, documentos, estado de cobro) y el envio.
 */
class PreAlertService
{
    public function __construct(private readonly ShipmentNotifier $notifier) {}

    /** Por que no se puede enviar ahora (null = se puede). */
    public function blockReason(Shipment $shipment): ?string
    {
        if (! $shipment->fecha_eta) {
            return 'La RO todavía no tiene ETA. Operaciones debe registrarla primero.';
        }

        if (! $shipment->isBeforeArrival()) {
            return 'La carga ya arribó: la pre-alerta ya no aplica.';
        }

        if ($this->pendingEtaChange($shipment)) {
            return 'Hay un cambio de ETA por validar. Valídalo o descártalo primero, así el cliente y la agencia reciben la misma fecha.';
        }

        return null;
    }

    public function pendingEtaChange(Shipment $shipment): ?ShipmentDateChange
    {
        return $shipment->pendingDateChanges()->where('campo', 'fecha_eta')->first();
    }

    /**
     * Proveedores de la RO (por sus provisiones), con su correo y si van
     * marcados por defecto (agencia de aduana y transportista).
     *
     * @return Collection<int, array{supplier: Supplier, checked: bool}>
     */
    public function supplierOptions(Shipment $shipment): Collection
    {
        return Supplier::query()
            ->whereIn('id', Provision::query()->where('shipment_id', $shipment->id)->select('supplier_id'))
            ->whereNotIn('tipo', ['naviera', 'aerolinea'])
            ->orderBy('razon_social')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'supplier' => $supplier,
                'checked' => filled($supplier->correo) && in_array($supplier->tipo, ShipmentPreAlert::DEFAULT_SUPPLIER_TYPES, true),
            ])
            ->sortByDesc(fn (array $option) => in_array($option['supplier']->tipo, ShipmentPreAlert::DEFAULT_SUPPLIER_TYPES, true))
            ->values();
    }

    /** Tipos clave (agencia de aduana, transportista) que faltan en las provisiones o no tienen correo. */
    public function missingKeySuppliers(Collection $options): array
    {
        $missing = [];

        foreach (ShipmentPreAlert::DEFAULT_SUPPLIER_TYPES as $type) {
            $ofType = $options->filter(fn (array $option) => $option['supplier']->tipo === $type);
            $label = Supplier::TYPE_LABELS[$type];

            if ($ofType->isEmpty()) {
                $missing[] = "No hay {$label} en las provisiones de esta RO.";
            } elseif ($ofType->every(fn (array $option) => blank($option['supplier']->correo))) {
                $missing[] = "{$ofType->first()['supplier']->razon_social} ({$label}) no tiene correo registrado.";
            }
        }

        return $missing;
    }

    /**
     * Documentos que se pueden adjuntar (BL, Borrador de BL, Commercial Invoice,
     * Packing List). Marcados por defecto los que no estan observados ni por validar.
     *
     * @return Collection<int, array{document: Document, checked: bool, note: ?string}>
     */
    public function documentOptions(Shipment $shipment): Collection
    {
        return $shipment->documents()
            ->get()
            ->filter(fn (Document $document) => ShipmentPreAlert::isAttachableType($document->tipo_documento))
            ->map(fn (Document $document) => [
                'document' => $document,
                'checked' => ! $document->isObserved() && ! $document->isPendingValidation(),
                'note' => match (true) {
                    $document->isObserved() => 'Observado',
                    $document->isPendingValidation() => 'Por validar',
                    default => null,
                },
            ])
            ->values();
    }

    /**
     * Estado de cobro para decidir si se retiene: condicion de credito y
     * facturas por cobrar (pendientes o emitidas) del cliente, por moneda.
     */
    public function creditSummary(Shipment $shipment): array
    {
        $client = $shipment->client;
        $openStates = ['pendiente', 'emitida'];

        $totals = fn ($query) => $query->whereIn('estado', $openStates)
            ->selectRaw('moneda, COUNT(*) as cantidad, SUM(monto) as total')
            ->groupBy('moneda')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->moneda => ['count' => (int) $row->cantidad, 'total' => (float) $row->total]])
            ->all();

        return [
            'credit_label' => $shipment->creditLabel(),
            'is_cash' => $shipment->condicion_credito === Client::CREDIT_CASH,
            // Sin el orden de la relacion: MySQL (only_full_group_by) no lo acepta con GROUP BY.
            'this_ro' => $totals(Invoice::query()->where('shipment_id', $shipment->id)),
            'client' => $client ? $totals(Invoice::query()->whereIn('shipment_id', Shipment::query()->where('client_id', $client->id)->select('id'))) : [],
        ];
    }

    /**
     * Envia la pre-alerta: un correo al cliente y uno por proveedor o correo
     * extra (nadie ve los correos de los demas). Queda en el historial.
     *
     * @param  array{enviar_cliente: bool, supplier_ids: array<int>, correos_extra: array<string>, document_ids: array<int>, nota: ?string}  $data
     */
    public function send(Shipment $shipment, User $user, array $data): ShipmentPreAlert
    {
        $shipment->loadMissing(['client', 'customerService', 'containers']);

        $clientEmails = $data['enviar_cliente'] ? $shipment->clientRecipientEmails() : [];
        $suppliers = Supplier::query()->whereIn('id', $data['supplier_ids'])->whereNotNull('correo')->orderBy('razon_social')->get();
        $documents = $shipment->documents()->whereIn('id', $data['document_ids'])->get();

        $recipients = array_values(array_filter([
            $clientEmails !== [] ? ['tipo' => 'cliente', 'nombre' => 'Cliente', 'correos' => $clientEmails] : null,
            ...$suppliers->map(fn (Supplier $supplier) => [
                'tipo' => $supplier->tipo,
                'nombre' => $supplier->razon_social,
                'correos' => [$supplier->correo],
            ])->all(),
            ...array_map(fn (string $email) => ['tipo' => 'otro', 'nombre' => $email, 'correos' => [$email]], $data['correos_extra']),
        ]));

        return DB::transaction(function () use ($shipment, $user, $data, $clientEmails, $suppliers, $documents, $recipients) {
            $preAlert = $shipment->preAlerts()->create([
                'estado' => ShipmentPreAlert::STATUS_SENT,
                'eta_enviada' => $shipment->fecha_eta->toDateString(),
                'destinatarios' => $recipients,
                'documentos' => $documents->map(fn (Document $document) => ['id' => $document->id, 'tipo' => $document->tipo_documento, 'nombre' => $document->nombre_original])->values()->all(),
                'nota' => $data['nota'],
                'user_id' => $user->id,
            ]);

            $this->notifier->preAlert($shipment, $user, $clientEmails, $suppliers, $data['correos_extra'], $documents, $data['nota']);

            ShipmentEvent::recordAudit($shipment, sprintf(
                'Pre-alerta de llegada enviada (ETA %s) a %s%s.',
                $shipment->fecha_eta->format('d/m/Y'),
                $preAlert->recipientsSummary(),
                $documents->isNotEmpty() ? ' con '.$documents->pluck('tipo_documento')->join(', ', ' y ') : ''
            ), $user->id);

            return $preAlert;
        });
    }

    public function hold(Shipment $shipment, User $user, string $reason): ShipmentPreAlert
    {
        return DB::transaction(function () use ($shipment, $user, $reason) {
            $preAlert = $shipment->preAlerts()->create([
                'estado' => ShipmentPreAlert::STATUS_HELD,
                'eta_enviada' => null,
                'motivo_retencion' => $reason,
                'user_id' => $user->id,
            ]);

            ShipmentEvent::recordAudit($shipment, "Pre-alerta de llegada retenida: {$reason}", $user->id);

            return $preAlert;
        });
    }

    /** Peso total en bytes de los documentos (lo que realmente pesa el archivo guardado). */
    public function attachmentsSize(Collection $documents): int
    {
        $disk = Storage::disk('private');

        return (int) $documents->sum(fn (Document $document) => $disk->exists($document->ruta) ? $disk->size($document->ruta) : 0);
    }
}
