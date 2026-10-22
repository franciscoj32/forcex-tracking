<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Costo de proveedor de un embarque (lo registra Operaciones):
 * factura del proveedor, operacion bancaria del pago, monto y moneda.
 */
class Provision extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pendiente';
    public const STATUS_PAID = 'pagado';

    public const CURRENCIES = ['USD', 'PEN'];

    protected $fillable = [
        'shipment_id',
        'supplier_id',
        'rate_concept_id',
        'descripcion',
        'monto',
        'moneda',
        'nro_factura_proveedor',
        'nro_operacion_bancaria',
        'fecha_pago',
        'estado',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_pago' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(RateConcept::class, 'rate_concept_id');
    }

    public function isPaid(): bool
    {
        return $this->estado === self::STATUS_PAID;
    }
}
