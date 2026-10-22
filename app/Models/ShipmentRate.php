<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tarifa de venta pactada con el cliente para una RO. */
class ShipmentRate extends Model
{
    public const CURRENCIES = ['USD', 'PEN'];

    protected $fillable = ['shipment_id', 'rate_concept_id', 'monto', 'moneda', 'observacion'];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(RateConcept::class, 'rate_concept_id');
    }
}
