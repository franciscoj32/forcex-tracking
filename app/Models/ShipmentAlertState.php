<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que una persona hizo con una alerta: "vista" o "pospuesta".
 * Las alertas se calculan al vuelo (App\Services\Alerts\AlertDetector);
 * esta tabla solo guarda las acciones, identificadas por RO + tipo + huella.
 */
class ShipmentAlertState extends Model
{
    use Prunable;

    public const ACTION_SEEN = 'vista';
    public const ACTION_SNOOZED = 'pospuesta';

    protected $fillable = [
        'shipment_id',
        'tipo',
        'huella',
        'accion',
        'nivel',
        'pospuesta_hasta',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'pospuesta_hasta' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function key(): string
    {
        return "{$this->shipment_id}|{$this->tipo}|{$this->huella}";
    }

    /** Las acciones viejas ya no apuntan a ninguna alerta: se limpian solas. */
    public function prunable(): Builder
    {
        return static::where('updated_at', '<', now()->subDays(90));
    }
}
