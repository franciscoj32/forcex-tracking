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

    /**
     * Desarrollo: cuenta tecnica oculta, por encima del Administrador / Gerente.
     * Tiene todos sus permisos y ademas maneja la marca y los modulos.
     * No esta en ROLES ni en INTERNAL_ROLES: no se puede asignar desde el
     * formulario y no aparece en listas, selectores ni correos del negocio.
     */
    public const ROLE_DEVELOPER = 'desarrollo';

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
        'fecha_nacimiento',
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
            'fecha_nacimiento' => 'date',
        ];
    }

    /**
     * ¿Hoy es su cumpleanos? Quien nacio un 29 de febrero lo celebra el 28
     * en los anos que no son bisiestos.
     */
    public function isBirthdayOn(?\DateTimeInterface $day = null): bool
    {
        if (! $this->fecha_nacimiento) {
            return false;
        }

        $day = \Illuminate\Support\Carbon::instance($day ?? today());
        $month = (int) $this->fecha_nacimiento->month;
        $date = (int) $this->fecha_nacimiento->day;

        if ($month === 2 && $date === 29 && ! $day->isLeapYear()) {
            $date = 28;
        }

        return $day->month === $month && $day->day === $date;
    }

    /** "15 de marzo": sin el ano, para mostrarlo al equipo. */
    public function birthdayLabel(): ?string
    {
        return $this->fecha_nacimiento?->locale('es')->translatedFormat('j \d\e F');
    }

    /** Primer nombre, para saludos. */
    public function firstName(): string
    {
        return \Illuminate\Support\Str::before(trim($this->name), ' ') ?: $this->name;
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'asignado_a_id');
    }

    public static function roleLabel(?string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ($role === self::ROLE_DEVELOPER ? 'Desarrollo' : ucfirst((string) $role));
    }

    /**
     * Rol con el que se aplican los permisos por rol: Desarrollo trabaja con
     * los del Administrador (tablas de campos editables, tareas del Inicio).
     */
    public function permissionRole(): string
    {
        return $this->isDeveloper() ? self::ROLE_ADMIN : (string) $this->role;
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

    /** Administrador / Gerente. Desarrollo tambien cuenta como Administrador. */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->isDeveloper();
    }

    /** Cuenta oculta de Desarrollo: marca, modulos y todo lo del Administrador. */
    public function isDeveloper(): bool
    {
        return $this->role === self::ROLE_DEVELOPER;
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
        return in_array($this->role, self::INTERNAL_ROLES, true) || $this->isDeveloper();
    }

    /** Desarrollo cumple cualquier permiso que tenga el Administrador. */
    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return in_array($this->role, $roles, true)
            || ($this->isDeveloper() && in_array(self::ROLE_ADMIN, $roles, true));
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
