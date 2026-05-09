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

    public const ROLE_ADMIN = 'admin';
    public const ROLE_OPERACIONES = 'operaciones';
    public const ROLE_COMERCIAL = 'comercial';
    public const ROLE_CLIENTE = 'cliente';

    public const ESTADO_ACTIVO = 'activo';
    public const ESTADO_INACTIVO = 'inactivo';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_OPERACIONES,
        self::ROLE_COMERCIAL,
        self::ROLE_CLIENTE,
    ];

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'client_id',
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
        ];
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

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles, true);
    }

    public function isActive(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }
}
