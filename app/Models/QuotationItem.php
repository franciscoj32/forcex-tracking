<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Linea de tarifa de una cotizacion (flete, THC, despacho, etc.). */
class QuotationItem extends Model
{
    protected $fillable = [
        'rate_concept_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'moneda',
        'afecto_igv',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'afecto_igv' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(RateConcept::class, 'rate_concept_id');
    }

    /** Cantidad x precio, sin IGV. */
    public function total(): float
    {
        return round((float) $this->cantidad * (float) $this->precio_unitario, 2);
    }
}
