<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    public const CATEGORY_OPERATIVO = 'operativo';
    public const CATEGORY_ENDOSO = 'endoso';
    public const CATEGORY_ADUANAS = 'aduanas';
    public const CATEGORY_FACTURACION = 'facturacion';

    public const CATEGORIES = [
        self::CATEGORY_OPERATIVO,
        self::CATEGORY_ENDOSO,
        self::CATEGORY_ADUANAS,
        self::CATEGORY_FACTURACION,
    ];

    public const TYPE_SUGGESTIONS = [
        self::CATEGORY_OPERATIVO => [
            'BL',
            'Packing List',
            'Commercial Invoice',
            'Certificado',
            'Foto de carga',
        ],
        self::CATEGORY_ENDOSO => [
            'BL Endosado',
            'Carta de Endoso',
            'Instruccion de Endoso',
            'Cargo de Endoso',
        ],
        self::CATEGORY_ADUANAS => [
            'DAM',
            'Transmision Aduanas',
            'Levante',
            'Sustento Aduanas',
        ],
        self::CATEGORY_FACTURACION => [
            'Factura cliente',
            'Sustento de cobro',
            'Comprobante',
        ],
    ];

    protected $fillable = [
        'shipment_id',
        'categoria',
        'tipo_documento',
        'nombre_original',
        'nombre_archivo',
        'ruta',
        'peso',
        'mime_type',
        'user_id',
    ];

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

    public function isEndorsement(): bool
    {
        return $this->categoria === self::CATEGORY_ENDOSO;
    }
}
