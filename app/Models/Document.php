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

    /*
    | Validacion documental: los documentos que envia el cliente los revisa el
    | Customer Service asignado antes de usarlos en la operacion.
    */
    public const VALIDATION_PENDING = 'pendiente';
    public const VALIDATION_APPROVED = 'aprobado';
    public const VALIDATION_OBSERVED = 'observado';

    public const VALIDATION_LABELS = [
        self::VALIDATION_PENDING => 'Por validar',
        self::VALIDATION_APPROVED => 'Aprobado',
        self::VALIDATION_OBSERVED => 'Observado',
    ];

    /** Tipos que el Customer debe validar. */
    public const VALIDATABLE_TYPES = [
        'Commercial Invoice',
        'Packing List',
        'Borrador de BL',
    ];

    public const TYPE_SUGGESTIONS = [
        self::CATEGORY_OPERATIVO => [
            'Borrador de BL',
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
        'estado_validacion',
        'observacion_validacion',
        'validado_por_id',
        'validado_en',
    ];

    protected function casts(): array
    {
        return [
            'validado_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Un documento de tipo validable entra siempre como "por validar".
        static::creating(function (self $document) {
            if ($document->estado_validacion === null && static::requiresValidation($document->tipo_documento)) {
                $document->estado_validacion = self::VALIDATION_PENDING;
            }
        });
    }

    public static function requiresValidation(?string $type): bool
    {
        return in_array(mb_strtolower(trim((string) $type)), array_map('mb_strtolower', self::VALIDATABLE_TYPES), true);
    }

    public function needsValidation(): bool
    {
        return $this->estado_validacion !== null;
    }

    public function isPendingValidation(): bool
    {
        return $this->estado_validacion === self::VALIDATION_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->estado_validacion === self::VALIDATION_APPROVED;
    }

    public function isObserved(): bool
    {
        return $this->estado_validacion === self::VALIDATION_OBSERVED;
    }

    public function validationLabel(): ?string
    {
        return self::VALIDATION_LABELS[$this->estado_validacion] ?? null;
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_id');
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

    public function isEndorsement(): bool
    {
        return $this->categoria === self::CATEGORY_ENDOSO;
    }
}
