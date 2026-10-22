<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cambio de ETD/ETA registrado por Operaciones que espera la validacion del
 * Customer antes de mostrarse al cliente.
 */
class ShipmentDateChange extends Model
{
    public const STATUS_PENDING = 'pendiente';
    public const STATUS_APPROVED = 'aprobado';
    public const STATUS_DISCARDED = 'descartado';
    public const STATUS_SUPERSEDED = 'reemplazado';

    /** Campo interno => campo publicado al cliente. */
    public const FIELDS = [
        'fecha_etd' => 'fecha_etd_cliente',
        'fecha_eta' => 'fecha_eta_cliente',
    ];

    public const FIELD_LABELS = [
        'fecha_etd' => 'ETD',
        'fecha_eta' => 'ETA',
    ];

    protected $fillable = [
        'shipment_id',
        'campo',
        'valor_anterior',
        'valor_nuevo',
        'valor_publicado',
        'estado',
        'cambiado_por_id',
        'revisado_por_id',
        'revisado_en',
        'nota_cliente',
        'notificado_en',
        'lote',
    ];

    protected function casts(): array
    {
        return [
            'valor_anterior' => 'date',
            'valor_nuevo' => 'date',
            'valor_publicado' => 'date',
            'revisado_en' => 'datetime',
            'notificado_en' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cambiado_por_id')->withDefault(['name' => 'Sistema']);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('estado', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->estado === self::STATUS_PENDING;
    }

    public function fieldLabel(): string
    {
        return self::FIELD_LABELS[$this->campo] ?? $this->campo;
    }

    /** Campo de la RO que ve el cliente (fecha_etd_cliente / fecha_eta_cliente). */
    public function publishedField(): string
    {
        return self::FIELDS[$this->campo];
    }

    /** Diferencia en dias entre lo publicado y lo nuevo (positivo = retraso). */
    public function delayInDays(): ?int
    {
        if (! $this->valor_publicado || ! $this->valor_nuevo) {
            return null;
        }

        return (int) $this->valor_publicado->diffInDays($this->valor_nuevo, false);
    }
}
