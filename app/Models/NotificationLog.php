<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bitacora de correos automaticos: a quien se envio, cuando y si fallo. */
class NotificationLog extends Model
{
    public const STATUS_QUEUED = 'en_cola';
    public const STATUS_SENT = 'enviado';
    public const STATUS_FAILED = 'fallido';
    public const STATUS_NO_RECIPIENTS = 'sin_destinatarios';

    public const EVENT_LABELS = [
        'ro_registrada' => 'A · RO registrada',
        'cambio_fecha_pendiente' => 'B · Cambio de fecha por validar',
        'reprogramacion' => 'B · Reprogramacion al cliente',
        'carga_arribo' => 'C · Carga en destino / tarja',
        'pre_alerta' => 'Pre-alerta de llegada',
        'nota_cliente' => 'Nota del Customer',
        'cliente_pendiente' => 'Cliente pendiente de aprobacion',
        'cliente_revisado' => 'Cliente aprobado / rechazado',
        'alerta_diaria' => 'Alertas diarias',
        'cotizacion' => 'Cotización enviada',
    ];

    protected $fillable = [
        'shipment_id',
        'client_id',
        'evento',
        'asunto',
        'destinatarios',
        'responder_a',
        'estado',
        'error',
        'intentos',
        'enviado_en',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'destinatarios' => 'array',
            'enviado_en' => 'datetime',
            'intentos' => 'integer',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function eventLabel(): string
    {
        return self::EVENT_LABELS[$this->evento] ?? $this->evento;
    }
}
