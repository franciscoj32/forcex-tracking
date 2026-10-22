<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Accion del Customer sobre la Pre-alerta de llegada de una RO: enviada a los
 * externos o retenida (por ejemplo, el cliente tiene facturas por pagar).
 * La ultima fila de la RO es su estado actual.
 */
class ShipmentPreAlert extends Model
{
    public const STATUS_SENT = 'enviada';
    public const STATUS_HELD = 'retenida';

    /** Documentos que se pueden adjuntar al correo de la agencia. */
    public const ATTACHABLE_TYPES = ['BL', 'Borrador de BL', 'Commercial Invoice', 'Packing List'];

    /** Proveedores marcados por defecto (los demas de la RO quedan opcionales). */
    public const DEFAULT_SUPPLIER_TYPES = ['agencia_aduana', 'transportista'];

    /** Limite de adjuntos por correo (el SMTP del hosting suele cortar en 10-25 MB). */
    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    public const MAX_EXTRA_EMAILS = 3;

    protected $fillable = [
        'shipment_id',
        'estado',
        'eta_enviada',
        'destinatarios',
        'documentos',
        'nota',
        'motivo_retencion',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'eta_enviada' => 'date',
            'destinatarios' => 'array',
            'documentos' => 'array',
        ];
    }

    public function isSent(): bool
    {
        return $this->estado === self::STATUS_SENT;
    }

    public function isHeld(): bool
    {
        return $this->estado === self::STATUS_HELD;
    }

    public static function isAttachableType(?string $type): bool
    {
        return in_array(mb_strtolower(trim((string) $type)), array_map('mb_strtolower', self::ATTACHABLE_TYPES), true);
    }

    /** "Cliente, Agencia de Aduanas Callao Express y 1 correo más" para el historial. */
    public function recipientsSummary(): string
    {
        $names = collect($this->destinatarios ?? [])->pluck('nombre')->filter()->unique()->values();

        if ($names->count() <= 1) {
            return (string) $names->first();
        }

        return $names->slice(0, -1)->join(', ').' y '.$names->last();
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'Usuario eliminado']);
    }
}
