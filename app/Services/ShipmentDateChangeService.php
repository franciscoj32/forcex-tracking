<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentDateChange;
use App\Models\ShipmentEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Flujo de validacion de ETD/ETA.
 *
 * 1. Operaciones cambia la fecha interna -> se registra un cambio "pendiente".
 * 2. El cliente sigue viendo la fecha publicada (fecha_*_cliente).
 * 3. El Customer aprueba (se publica al cliente) o descarta (falsa alarma).
 *
 * Correos: B1 al Customer cuando hay un cambio pendiente; B2 al cliente cuando
 * el Customer lo aprueba (confirmacion de la primera fecha o reprogramacion).
 */
class ShipmentDateChangeService
{
    public function __construct(private readonly ShipmentNotifier $notifier)
    {
    }

    /**
     * Registra los cambios de ETD/ETA de un embarque recien guardado.
     * Lo llama ShipmentObserver despues de crear o actualizar la RO.
     */
    public function track(Shipment $shipment, bool $wasCreated, ?int $userId): void
    {
        foreach (ShipmentDateChange::FIELDS as $field => $publishedField) {
            if (! $wasCreated && ! $shipment->wasChanged($field)) {
                continue;
            }

            $newValue = $this->dateString($shipment->{$field});
            $previousValue = $wasCreated ? null : $this->dateString($shipment->getOriginal($field));
            $publishedValue = $this->dateString($shipment->{$publishedField});

            // Un cambio nuevo reemplaza al pendiente anterior del mismo campo:
            // el Customer solo valida la ultima fecha informada por Operaciones.
            $shipment->dateChanges()
                ->where('campo', $field)
                ->where('estado', ShipmentDateChange::STATUS_PENDING)
                ->update(['estado' => ShipmentDateChange::STATUS_SUPERSEDED]);

            // Si la fecha vuelve a la ya publicada, no hay nada que validar.
            if ($newValue === $publishedValue) {
                continue;
            }

            $change = $shipment->dateChanges()->create([
                'campo' => $field,
                'valor_anterior' => $previousValue,
                'valor_nuevo' => $newValue,
                'valor_publicado' => $publishedValue,
                'estado' => ShipmentDateChange::STATUS_PENDING,
                'cambiado_por_id' => $userId,
                'lote' => self::$batch,
            ]);

            // Dentro de una actualizacion masiva el aviso se manda al final,
            // un solo correo por Customer (ver batch()).
            if (self::$batch !== null) {
                self::$batchChanges[] = $change;

                continue;
            }

            // Evento B1: se avisa al Customer para que valide.
            // Al crear la RO no se avisa: el correo A ya lo informa.
            if (! $wasCreated) {
                $this->notifier->dateChangePending($change);
            }
        }
    }

    /** Lote en curso de una actualizacion masiva (null fuera de ella). */
    private static ?string $batch = null;

    /** @var array<int, ShipmentDateChange> */
    private static array $batchChanges = [];

    /**
     * Ejecuta una actualizacion masiva: los cambios de ETD/ETA que se generen
     * quedan con el mismo lote y no envian B1 uno por uno. Al terminar se envia
     * un solo B1 resumido por Customer.
     *
     * @return \Illuminate\Support\Collection<int, ShipmentDateChange> cambios creados
     */
    public function batch(string $lote, \Closure $callback): \Illuminate\Support\Collection
    {
        self::$batch = $lote;
        self::$batchChanges = [];

        try {
            $callback();
            $changes = collect(self::$batchChanges);
        } finally {
            self::$batch = null;
            self::$batchChanges = [];
        }

        if ($changes->isNotEmpty()) {
            $this->notifier->dateChangesPendingBatch($changes, $lote);
        }

        return $changes;
    }

    /** El Customer valida el cambio: la fecha pasa a ser visible para el cliente. */
    public function approve(ShipmentDateChange $change, User $reviewer, ?string $clientNote = null): ShipmentDateChange
    {
        $this->ensurePending($change);

        return DB::transaction(function () use ($change, $reviewer, $clientNote) {
            $shipment = $change->shipment;
            $publishedField = $change->publishedField();
            $previousPublished = $shipment->{$publishedField};

            $shipment->forceFill([$publishedField => $change->valor_nuevo])->save();

            $change->forceFill([
                'estado' => ShipmentDateChange::STATUS_APPROVED,
                'revisado_por_id' => $reviewer->id,
                'revisado_en' => now(),
                'nota_cliente' => filled($clientNote) ? trim($clientNote) : null,
                'notificado_en' => now(),
            ])->save();

            $previousLabel = $previousPublished?->format('d/m/Y');
            $newLabel = $change->valor_nuevo?->format('d/m/Y') ?? 'por confirmar';

            ShipmentEvent::recordAudit($shipment, sprintf(
                '%s validado por Customer y publicado al cliente: %s -> %s.',
                $change->fieldLabel(),
                $previousLabel ?? 'sin fecha',
                $newLabel
            ), $reviewer->id);

            // En el tracking publico: la fecha + la nota del Customer.
            $label = $change->campo === 'fecha_eta' ? 'llegada' : 'salida';
            $prefix = $previousPublished === null
                ? "Fecha estimada de {$label} confirmada: {$newLabel}."
                : "Nueva fecha estimada de {$label}: {$newLabel}.";

            ShipmentEvent::recordClientNote($shipment, trim($prefix.' '.($change->nota_cliente ?? '')), $reviewer->id);

            // Evento B2: aviso al cliente (confirmacion o reprogramacion).
            $this->notifier->dateChangeApproved($change, $previousLabel);

            return $change;
        });
    }

    /** El Customer descarta el cambio: el cliente no recibe ningun aviso. */
    public function discard(ShipmentDateChange $change, User $reviewer, ?string $reason = null): ShipmentDateChange
    {
        $this->ensurePending($change);

        $change->forceFill([
            'estado' => ShipmentDateChange::STATUS_DISCARDED,
            'revisado_por_id' => $reviewer->id,
            'revisado_en' => now(),
            'nota_cliente' => filled($reason) ? trim($reason) : null,
        ])->save();

        return $change;
    }

    private function ensurePending(ShipmentDateChange $change): void
    {
        if (! $change->isPending()) {
            throw new InvalidArgumentException('Este cambio de fecha ya fue revisado o reemplazado por uno mas reciente.');
        }
    }

    private function dateString(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return filled($value) ? substr((string) $value, 0, 10) : null;
    }
}
