<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'numero',
        'monto',
        'moneda',
        'estado',
        'archivo_pdf',
    ];

    public const CURRENCIES = ['USD', 'PEN'];

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
}
