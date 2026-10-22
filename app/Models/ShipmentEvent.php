<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bitacora del embarque.
 *
 * - TYPE_STATE: hito del tracking (Booking, ATD, Arribo...). Visible al cliente
 *   con un texto estandar; la descripcion que escribe Operaciones es interna.
 * - TYPE_AUDIT: cambios internos de la ficha. Nunca visible al cliente.
 * - TYPE_CLIENT_NOTE: mensaje redactado por el Customer para el cliente.
 */
class ShipmentEvent extends Model
{
    use HasFactory;

    public const TYPE_STATE = 'estado';
    public const TYPE_AUDIT = 'auditoria';
    public const TYPE_CLIENT_NOTE = 'nota_cliente';

    public const TYPE_LABELS = [
        self::TYPE_STATE => 'Hito',
        self::TYPE_AUDIT => 'Interno',
        self::TYPE_CLIENT_NOTE => 'Nota al cliente',
    ];

    protected $fillable = [
        'shipment_id',
        'estado',
        'tipo',
        'visible_cliente',
        'fecha',
        'descripcion',
        'user_id',
    ];

    protected $attributes = [
        'tipo' => self::TYPE_AUDIT,
        'visible_cliente' => false,
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'visible_cliente' => 'boolean',
        ];
    }

    /** Hito del tracking: visible al cliente con su texto estandar. */
    public static function recordState(Shipment $shipment, string $state, mixed $date, ?string $internalNote, ?int $userId): self
    {
        return static::record([
            'shipment_id' => $shipment->id,
            'estado' => $state,
            'tipo' => self::TYPE_STATE,
            'visible_cliente' => true,
            'fecha' => $date,
            'descripcion' => $internalNote,
            'user_id' => $userId,
        ]);
    }

    /** Bitacora interna (cambios de ficha, contenedores, validaciones). */
    public static function recordAudit(Shipment $shipment, string $description, ?int $userId): self
    {
        return static::record([
            'shipment_id' => $shipment->id,
            'estado' => $shipment->estado_actual,
            'tipo' => self::TYPE_AUDIT,
            'visible_cliente' => false,
            'fecha' => now(),
            'descripcion' => $description,
            'user_id' => $userId,
        ]);
    }

    /** Mensaje del Customer para el cliente (aparece en el tracking publico). */
    public static function recordClientNote(Shipment $shipment, string $note, ?int $userId): self
    {
        return static::record([
            'shipment_id' => $shipment->id,
            'estado' => $shipment->estado_actual,
            'tipo' => self::TYPE_CLIENT_NOTE,
            'visible_cliente' => true,
            'fecha' => now(),
            'descripcion' => trim($note),
            'user_id' => $userId,
        ]);
    }

    /**
     * Registra un evento. El indice unico es (shipment_id, estado, fecha, tipo):
     * si ya hay un evento igual en el mismo segundo, se agrega la descripcion
     * a ese evento en lugar de fallar.
     */
    public static function record(array $attributes): self
    {
        $fecha = Carbon::parse($attributes['fecha'] ?? now())->startOfSecond();
        $tipo = $attributes['tipo'] ?? self::TYPE_AUDIT;

        $existing = static::query()
            ->where('shipment_id', $attributes['shipment_id'])
            ->where('estado', $attributes['estado'])
            ->where('tipo', $tipo)
            ->where('fecha', $fecha)
            ->first();

        if (! $existing) {
            return static::create(['fecha' => $fecha, 'tipo' => $tipo] + $attributes);
        }

        $existing->descripcion = trim(($existing->descripcion ?? '').' '.($attributes['descripcion'] ?? ''));
        $existing->save();

        return $existing;
    }

    public function scopeVisibleToClient(Builder $query): Builder
    {
        return $query->where('visible_cliente', true);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'Usuario eliminado',
        ]);
    }

    public function isClientNote(): bool
    {
        return $this->tipo === self::TYPE_CLIENT_NOTE;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->tipo] ?? $this->tipo;
    }

    /**
     * Texto que ve el cliente: el estandar del hito o la nota del Customer.
     * Nunca la descripcion interna de Operaciones.
     */
    public function clientText(): string
    {
        if ($this->isClientNote()) {
            return (string) $this->descripcion;
        }

        return Shipment::clientStateText($this->estado);
    }
}
