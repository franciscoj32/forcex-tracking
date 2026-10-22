<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    | El valor guardado en BD es corto y estable. La etiqueta visible vive en
    | ROLE_LABELS, asi se puede renombrar en pantalla sin romper el codigo.
    */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_COMERCIAL = 'comercial';
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_OPERACIONES = 'operaciones';
    public const ROLE_CLIENTE = 'cliente';

    public const ESTADO_ACTIVO = 'activo';
    public const ESTADO_INACTIVO = 'inactivo';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_COMERCIAL,
        self::ROLE_CUSTOMER,
        self::ROLE_OPERACIONES,
        self::ROLE_CLIENTE,
    ];

    /** Roles que trabajan dentro del backoffice. */
    public const INTERNAL_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_COMERCIAL,
        self::ROLE_CUSTOMER,
        self::ROLE_OPERACIONES,
    ];

    public const ROLE_LABELS = [
        self::ROLE_ADMIN => 'Administrador / Gerente',
        self::ROLE_COMERCIAL => 'Comercial',
        self::ROLE_CUSTOMER => 'Customer Service',
        self::ROLE_OPERACIONES => 'Operaciones',
        self::ROLE_CLIENTE => 'Cliente',
    ];

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'client_id',
        'acceso_portal',
        'estado',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'acceso_portal' => 'boolean',
        ];
    }

    public static function roleLabel(?string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ucfirst((string) $role);
    }

    public function getRoleLabelAttribute(): string
    {
        return static::roleLabel($this->role);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function shipmentEvents(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function shipmentPreferences(): HasMany
    {
        return $this->hasMany(UserShipmentPreference::class);
    }

    public function shipmentAssignments(): HasMany
    {
        return $this->hasMany(ShipmentUserAssignment::class);
    }

    public function assignedShipments(): BelongsToMany
    {
        return $this->belongsToMany(Shipment::class, 'shipment_user_assignments')
            ->withPivot(['assignment_type', 'alerts_enabled'])
            ->withTimestamps();
    }

    /** Embarques vendidos por este comercial. */
    public function commercialShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'comercial_id');
    }

    /** Embarques que coordina este Customer Service. */
    public function customerShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'customer_id');
    }

    /** Embarques que opera este usuario de Operaciones. */
    public function operatorShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'operador_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isComercial(): bool
    {
        return $this->role === self::ROLE_COMERCIAL;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isOperaciones(): bool
    {
        return $this->role === self::ROLE_OPERACIONES;
    }

    public function isInternal(): bool
    {
        return in_array($this->role, self::INTERNAL_ROLES, true);
    }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles, true);
    }

    public function isActive(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    /**
     * Solo los roles internos activos entran al backoffice.
     * El rol cliente queda preparado (acceso_portal) para un portal futuro.
     */
    public function canAccessBackoffice(): bool
    {
        return $this->isActive() && $this->isInternal();
    }
}
