<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    public const APPROVAL_PENDING = 'pendiente';
    public const APPROVAL_APPROVED = 'aprobado';
    public const APPROVAL_REJECTED = 'rechazado';

    public const APPROVAL_STATUSES = [
        self::APPROVAL_PENDING,
        self::APPROVAL_APPROVED,
        self::APPROVAL_REJECTED,
    ];

    public const APPROVAL_LABELS = [
        self::APPROVAL_PENDING => 'Pendiente de aprobacion',
        self::APPROVAL_APPROVED => 'Aprobado',
        self::APPROVAL_REJECTED => 'Rechazado',
    ];

    public const CREDIT_CASH = 'contado';
    public const CREDIT_TERM = 'credito';

    public const CREDIT_CONDITIONS = [self::CREDIT_CASH, self::CREDIT_TERM];

    public const CREDIT_DAYS_OPTIONS = [7, 15, 30, 45, 60, 90];

    public const CURRENCIES = ['USD', 'PEN'];

    protected $fillable = [
        'razon_social',
        'ruc',
        'correo',
        'telefono',
        'direccion',
        'estado',
        'estado_aprobacion',
        'registrado_por_id',
        'aprobado_por_id',
        'aprobado_en',
        'observacion_aprobacion',
        'condicion_credito',
        'dias_credito',
        'linea_credito',
        'moneda_linea_credito',
    ];

    protected function casts(): array
    {
        return [
            'aprobado_en' => 'datetime',
            'dias_credito' => 'integer',
            'linea_credito' => 'decimal:2',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class)
            ->orderByDesc('es_principal')
            ->orderBy('nombre');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id')->withDefault(['name' => 'Sin registro']);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    /** Clientes que pueden recibir nuevas RO: aprobados y activos. */
    public function scopeOperable(Builder $query): Builder
    {
        return $query
            ->where('estado_aprobacion', self::APPROVAL_APPROVED)
            ->where('estado', 'activo');
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('estado_aprobacion', self::APPROVAL_PENDING);
    }

    public function isApproved(): bool
    {
        return $this->estado_aprobacion === self::APPROVAL_APPROVED;
    }

    public function isPendingApproval(): bool
    {
        return $this->estado_aprobacion === self::APPROVAL_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->estado_aprobacion === self::APPROVAL_REJECTED;
    }

    public function canOperate(): bool
    {
        return $this->isApproved() && $this->estado === 'activo';
    }

    public function approvalLabel(): string
    {
        return self::APPROVAL_LABELS[$this->estado_aprobacion] ?? ucfirst((string) $this->estado_aprobacion);
    }

    public static function creditLabelFor(?string $condition, ?int $days): string
    {
        if ($condition === self::CREDIT_TERM) {
            return $days ? "Credito {$days} dias" : 'Credito';
        }

        return 'Pago contado';
    }

    public function creditLabel(): string
    {
        return static::creditLabelFor($this->condicion_credito, $this->dias_credito);
    }

    /** Correos de los contactos que reciben los avisos de la carga. */
    public function trackingRecipientEmails(): array
    {
        $contacts = $this->relationLoaded('contacts') ? $this->contacts : $this->contacts()->get();

        return $contacts
            ->where('recibe_tracking', true)
            ->pluck('correo')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
