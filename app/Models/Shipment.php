<?php

namespace App\Models;

use App\Observers\ShipmentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[ObservedBy(ShipmentObserver::class)]
class Shipment extends Model
{
    use HasFactory;

    private const TRACKING_STATE_DEFAULT_VISUAL = [
        'event_icon' => 'map-pinned',
        'route_icon' => null,
    ];

    public const TIPOS_CARGA = ['FCL', 'LCL', 'AEREO'];

    /** Incoterms 2020. */
    public const INCOTERMS = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

    public const ENDORSEMENT_STATUSES = ['pendiente', 'en_proceso', 'completado'];

    public const CHECKLIST_PRIORITY_REQUIRED = 'required';
    public const CHECKLIST_PRIORITY_RECOMMENDED = 'recommended';

    private const DOCUMENT_CHECKLIST_VISUALS = [
        Document::CATEGORY_OPERATIVO => [
            'label' => 'Operativo',
            'description' => 'Base documental para seguimiento diario del embarque.',
            'tone' => 'slate',
            'highlight' => 'border-slate-200 bg-slate-50/70',
            'badge' => 'bg-slate-100 text-slate-700',
        ],
        Document::CATEGORY_ENDOSO => [
            'label' => 'Endoso',
            'description' => 'Sustento documental para liberacion y control del endoso.',
            'tone' => 'amber',
            'highlight' => 'border-amber-200 bg-amber-50/70',
            'badge' => 'bg-amber-100 text-amber-700',
        ],
        Document::CATEGORY_ADUANAS => [
            'label' => 'Aduanas',
            'description' => 'Documentos que acompanan nacionalizacion, arribo y retiro.',
            'tone' => 'cyan',
            'highlight' => 'border-cyan-200 bg-cyan-50/70',
            'badge' => 'bg-cyan-100 text-cyan-700',
        ],
        Document::CATEGORY_FACTURACION => [
            'label' => 'Facturacion',
            'description' => 'Sustentos del cierre comercial, facturacion y cobranza.',
            'tone' => 'emerald',
            'highlight' => 'border-emerald-200 bg-emerald-50/70',
            'badge' => 'bg-emerald-100 text-emerald-700',
        ],
    ];

    public const CARGO_TYPE_VISUALS = [
        'FCL' => [
            'hero_icon' => 'ship',
            'route_icon' => 'ship',
        ],
        'LCL' => [
            'hero_icon' => 'ship',
            'route_icon' => 'ship',
        ],
        'AEREO' => [
            'hero_icon' => 'plane',
            'route_icon' => 'plane',
        ],
    ];

    public const COUNTRY_CODES = [
        'china' => 'cn',
        'peru' => 'pe',
        'perú' => 'pe',
        'usa' => 'us',
        'u s a' => 'us',
        'estados unidos' => 'us',
        'united states' => 'us',
        'mexico' => 'mx',
        'méxico' => 'mx',
        'colombia' => 'co',
        'ecuador' => 'ec',
        'chile' => 'cl',
        'argentina' => 'ar',
        'brasil' => 'br',
        'brazil' => 'br',
        'panama' => 'pa',
        'panamá' => 'pa',
        'espana' => 'es',
        'españa' => 'es',
        'spain' => 'es',
        'alemania' => 'de',
        'germany' => 'de',
        'italia' => 'it',
        'italy' => 'it',
        'francia' => 'fr',
        'france' => 'fr',
        'paises bajos' => 'nl',
        'países bajos' => 'nl',
        'netherlands' => 'nl',
        'japon' => 'jp',
        'japón' => 'jp',
        'japan' => 'jp',
        'corea del sur' => 'kr',
        'south korea' => 'kr',
        'hong kong' => 'hk',
        'singapur' => 'sg',
        'singapore' => 'sg',
        'taiwan' => 'tw',
        'india' => 'in',
        'canada' => 'ca',
        'canadá' => 'ca',
        'australia' => 'au',
        'emiratos arabes unidos' => 'ae',
        'emiratos árabes unidos' => 'ae',
        'united arab emirates' => 'ae',
        'dubai' => 'ae',
        'turquia' => 'tr',
        'turquía' => 'tr',
        'turkey' => 'tr',
        'reino unido' => 'gb',
        'united kingdom' => 'gb',
        'uk' => 'gb',
        'inglaterra' => 'gb',
        'thailandia' => 'th',
        'thailand' => 'th',
        'vietnam' => 'vn',
        'malasia' => 'my',
        'malaysia' => 'my',
        'indonesia' => 'id',
    ];

    public const COUNTRY_OPTIONS = [
        'Alemania',
        'Argentina',
        'Australia',
        'Brasil',
        'Canadá',
        'Chile',
        'China',
        'Colombia',
        'Corea del Sur',
        'Dubai',
        'Ecuador',
        'Emiratos Árabes Unidos',
        'España',
        'Estados Unidos',
        'Francia',
        'Hong Kong',
        'India',
        'Indonesia',
        'Italia',
        'Japón',
        'Malasia',
        'México',
        'Países Bajos',
        'Panamá',
        'Perú',
        'Reino Unido',
        'Singapur',
        'Taiwan',
        'Thailand',
        'Turquía',
        'Vietnam',
    ];

    public const TRACKING_STATES = [
        'Creacion Routing Order',
        'Booking Confirmado',
        'ATD',
        'En transito',
        'Transmision Aduanas',
        'ETA',
        'Arribo a puerto',
        'Descarga',
        'Ingreso a almacen',
        'Retiro de almacen',
        'Entregado',
        'Finalizado',
    ];

    /**
     * Texto estandar que ve el cliente por cada hito. Operaciones registra el
     * detalle tecnico, pero el cliente solo lee estos mensajes y las notas del
     * Customer.
     */
    public const CLIENT_STATE_TEXTS = [
        'Creacion Routing Order' => 'Recibimos tu orden y estamos coordinando la reserva de espacio.',
        'Booking Confirmado' => 'La reserva de espacio fue confirmada con la linea de transporte.',
        'ATD' => 'Tu carga salio del puerto o aeropuerto de origen.',
        'En transito' => 'Tu carga esta en transito internacional hacia el Peru.',
        'Transmision Aduanas' => 'Transmitimos la documentacion a Aduanas para la llegada de tu carga.',
        'ETA' => 'Tu carga esta proxima a arribar al Peru.',
        'Arribo a puerto' => 'Tu carga arribo al Peru.',
        'Descarga' => 'Tu carga fue descargada en el terminal.',
        'Ingreso a almacen' => 'Tu carga ingreso al almacen de direccionamiento.',
        'Retiro de almacen' => 'Tu carga fue retirada del almacen.',
        'Entregado' => 'Tu carga fue entregada.',
        'Finalizado' => 'Operacion finalizada. Gracias por confiar en nosotros.',
    ];

    public static function clientStateText(?string $state): string
    {
        return static::CLIENT_STATE_TEXTS[$state] ?? 'Actualizamos el estado de tu carga.';
    }

    public const TRACKING_STATE_VISUALS = [
        'Creacion Routing Order' => [
            'event_icon' => 'badge-check',
            'route_icon' => 'badge-check',
        ],
        'Booking Confirmado' => [
            'event_icon' => 'badge-check',
            'route_icon' => 'badge-check',
        ],
        'ATD' => [
            'event_icon' => 'ship',
            'route_icon' => 'ship',
            'cargo_overrides' => [
                'AEREO' => [
                    'event_icon' => 'plane-takeoff',
                    'route_icon' => 'plane-takeoff',
                ],
            ],
        ],
        'En transito' => [
            'event_icon' => 'ship',
            'route_icon' => 'ship',
            'cargo_overrides' => [
                'AEREO' => [
                    'event_icon' => 'plane',
                    'route_icon' => 'plane',
                ],
            ],
        ],
        'Transmision Aduanas' => [
            'event_icon' => 'file-check',
            'route_icon' => 'file-check',
        ],
        'ETA' => [
            'event_icon' => 'clock-3',
            'route_icon' => 'clock-3',
            'cargo_overrides' => [
                'AEREO' => [
                    'route_icon' => 'plane-landing',
                ],
            ],
        ],
        'Arribo a puerto' => [
            'event_icon' => 'anchor',
            'route_icon' => 'anchor',
            'cargo_overrides' => [
                'AEREO' => [
                    'event_icon' => 'plane-landing',
                    'route_icon' => 'plane-landing',
                ],
            ],
        ],
        'Descarga' => [
            'event_icon' => 'container',
            'route_icon' => 'container',
        ],
        'Ingreso a almacen' => [
            'event_icon' => 'warehouse',
            'route_icon' => 'warehouse',
        ],
        'Retiro de almacen' => [
            'event_icon' => 'truck',
            'route_icon' => 'truck',
        ],
        'Entregado' => [
            'event_icon' => 'package-check',
            'route_icon' => 'truck',
        ],
        'Finalizado' => [
            'event_icon' => 'package-check',
            'route_icon' => 'package-check',
        ],
    ];

    protected $fillable = [
        'client_id',
        'comercial_id',
        'customer_id',
        'operador_id',
        'nro_orden_trabajo',
        'routing_order',
        'bl',
        'tipo_carga',
        'incoterm',
        'condicion_credito',
        'dias_credito',
        'credito_forzado',
        'tipo_cambio',
        'origen',
        'puerto_carga',
        'puerto_descarga',
        'destino_final',
        'naviera',
        'nave',
        'viaje',
        'agente_origen',
        'consignatario',
        'peso',
        'volumen',
        'fecha_etd',
        'fecha_cut_off',
        'fecha_eta',
        'fecha_descarga',
        'dias_libres_demurrage',
        'dias_libres_detention',
        'ubicacion_actual',
        'ubicacion_latitud',
        'ubicacion_longitud',
        'almacen_direccionamiento',
        'fecha_tarja',
        'bultos_tarjados',
        'peso_tarjado_kg',
        'fecha_ingreso_almacen',
        'dias_libres_almacen',
        'observacion_tarja',
        'estado_actual',
        'estado_endoso',
        'fecha_endoso',
        'responsable_endoso_id',
        'observacion_endoso',
        'observaciones',
    ];

    protected static function booted(): void
    {
        static::creating(function (Shipment $shipment) {
            if (blank($shipment->tracking_code)) {
                $shipment->tracking_code = static::generateTrackingCode();
            }
        });

        static::saving(function (Shipment $shipment) {
            // "Donde esta ahora": se registra cuando se actualizo la ubicacion.
            if ($shipment->isDirty(['ubicacion_actual', 'ubicacion_latitud', 'ubicacion_longitud'])) {
                $shipment->ubicacion_actualizada_en = filled($shipment->ubicacion_actual) ? now() : null;
            }

            // Nave y viaje en mayusculas y sin espacios dobles: asi "msc anna" y
            // "MSC  ANNA" quedan en el mismo grupo de la actualizacion masiva.
            foreach (['nave', 'viaje'] as $vesselField) {
                if ($shipment->isDirty($vesselField)) {
                    $value = Str::upper(Str::squish((string) $shipment->{$vesselField}));
                    $shipment->{$vesselField} = $value !== '' ? $value : null;
                }
            }

            // La primera ETA informada queda como linea base del retraso y no se vuelve a tocar.
            if ($shipment->fecha_eta && ! $shipment->fecha_eta_original) {
                $shipment->fecha_eta_original = $shipment->fecha_eta;
            }

            // Si el booking se rola y cambia el cut-off, el "cumplido" anterior ya no vale.
            if ($shipment->exists
                && $shipment->isDirty('fecha_cut_off')
                && ! $shipment->isDirty('cut_off_cumplido_en')) {
                $shipment->cut_off_cumplido_en = null;
                $shipment->cut_off_cumplido_por_id = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'fecha_etd' => 'date',
            'fecha_eta' => 'date',
            'fecha_descarga' => 'date',
            'fecha_endoso' => 'date',
            'peso' => 'decimal:2',
            'volumen' => 'decimal:2',
            'dias_credito' => 'integer',
            'credito_forzado' => 'boolean',
            'fecha_cut_off' => 'datetime',
            'fecha_etd_cliente' => 'date',
            'fecha_eta_cliente' => 'date',
            'ubicacion_latitud' => 'decimal:7',
            'ubicacion_longitud' => 'decimal:7',
            'ubicacion_actualizada_en' => 'datetime',
            'fecha_tarja' => 'datetime',
            'bultos_tarjados' => 'integer',
            'peso_tarjado_kg' => 'decimal:2',
            'fecha_ingreso_almacen' => 'datetime',
            'tipo_cambio' => 'decimal:4',
            'fecha_eta_original' => 'date',
            'cut_off_cumplido_en' => 'datetime',
            'dias_libres_demurrage' => 'integer',
            'dias_libres_detention' => 'integer',
            'dias_libres_almacen' => 'integer',
        ];
    }

    /** Usuario que confirmo el cut-off cumplido. */
    public function cutOffConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cut_off_cumplido_por_id');
    }

    /** Antes del ATD: el cut-off todavia importa. */
    public function isBeforeDeparture(): bool
    {
        return $this->currentTrackingStateIndex() < static::trackingStateIndex('ATD');
    }

    /** Antes del arribo a puerto: la ETA todavia puede vencerse o retrasarse. */
    public function isBeforeArrival(): bool
    {
        return $this->currentTrackingStateIndex() < static::trackingStateIndex('Arribo a puerto');
    }

    /** Ya arribo a puerto (hay descarga o se puede usar la fecha del arribo). */
    public function hasArrived(): bool
    {
        return ! $this->isBeforeArrival();
    }

    /** La carga ya salio del almacen: el almacenaje dejo de correr. */
    public function hasLeftWarehouse(): bool
    {
        return $this->currentTrackingStateIndex() >= static::trackingStateIndex('Retiro de almacen');
    }

    /** Estados en los que la RO sigue viva (todo menos Finalizado). */
    public static function activeStates(): array
    {
        return array_values(array_diff(static::TRACKING_STATES, ['Finalizado']));
    }

    /**
     * RO cuyas alertas le tocan al usuario: es su Customer u Operador, o la
     * sigue con alertas activadas (Responsables / En seguimiento).
     */
    public function scopeAssignedForAlerts(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $assigned) use ($user) {
            $assigned
                ->where('shipments.customer_id', $user->id)
                ->orWhere('shipments.operador_id', $user->id)
                ->orWhereHas('userAssignments', fn (Builder $assignment) => $assignment
                    ->where('user_id', $user->id)
                    ->where('alerts_enabled', true));
        });
    }

    public function alertStates(): HasMany
    {
        return $this->hasMany(ShipmentAlertState::class);
    }

    /** El Operador puede confirmar el cut-off mientras la carga no zarpa. */
    public function canConfirmCutOff(): bool
    {
        return $this->fecha_cut_off !== null && $this->isBeforeDeparture();
    }

    /** Tarifas de venta pactadas con el cliente. */
    public function rates(): HasMany
    {
        return $this->hasMany(ShipmentRate::class)->orderBy('id');
    }

    /** Venta, costo y margen en USD de la RO. */
    public function financials(): \App\Support\ShipmentFinancials
    {
        return \App\Support\ShipmentFinancials::for($this);
    }

    /** Estados anteriores al arribo (para detectar ETA vencida). */
    public static function statesBeforeArrival(): array
    {
        return array_slice(static::TRACKING_STATES, 0, array_search('Arribo a puerto', static::TRACKING_STATES, true));
    }

    /** Retrasados: ETA vencida y la carga todavia no arriba. */
    public function scopeDelayed(Builder $query): Builder
    {
        return $query
            ->whereNotNull('fecha_eta')
            ->whereDate('fecha_eta', '<', now()->startOfDay())
            ->whereIn('estado_actual', static::statesBeforeArrival());
    }

    /** Arribaron al Peru pero Operaciones aun no registra la tarja. */
    public function scopeArrivedWithoutTarja(Builder $query): Builder
    {
        return $query
            ->whereIn('estado_actual', ['Arribo a puerto', 'Descarga', 'Ingreso a almacen'])
            ->whereNull('fecha_tarja')
            ->whereNull('fecha_ingreso_almacen');
    }

    public function daysLate(): int
    {
        return $this->fecha_eta ? max(0, (int) $this->fecha_eta->diffInDays(now()->startOfDay())) : 0;
    }

    /** Hitos y notas del Customer: lo unico que muestra el tracking publico. */
    public function clientVisibleEvents(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)
            ->where('visible_cliente', true)
            ->orderBy('fecha');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class)->latest('id');
    }

    /** Correos de los contactos del cliente que reciben avisos de la carga. */
    public function clientRecipientEmails(): array
    {
        // Usa el cliente ya cargado (la ficha lo pide varias veces por pagina),
        // salvo que se haya cargado sin el correo general (client:id,razon_social).
        $loaded = $this->relationLoaded('client') ? $this->client : null;
        $client = $loaded && array_key_exists('correo', $loaded->getAttributes()) ? $loaded : $this->client()->first();

        if (! $client) {
            return [];
        }

        $client->loadMissing('contacts');

        $emails = $client->trackingRecipientEmails();

        // Si no hay contactos de tracking, se usa el correo general del cliente.
        return $emails !== [] ? $emails : array_values(array_filter([$client->correo]));
    }

    public function containers(): HasMany
    {
        return $this->hasMany(ShipmentContainer::class)->orderBy('id');
    }

    public function dateChanges(): HasMany
    {
        return $this->hasMany(ShipmentDateChange::class)->latest('id');
    }

    public function pendingDateChanges(): HasMany
    {
        return $this->hasMany(ShipmentDateChange::class)
            ->where('estado', ShipmentDateChange::STATUS_PENDING)
            ->latest('id');
    }

    /** El peso se guarda en KG; en pantalla se muestra en toneladas. */
    public static function formatTons(mixed $kilograms): ?string
    {
        return $kilograms === null || $kilograms === '' ? null : number_format(((float) $kilograms) / 1000, 3).' TN';
    }

    public function weightInTons(): ?string
    {
        return static::formatTons($this->peso);
    }

    public function isAirCargo(): bool
    {
        return $this->tipo_carga === 'AEREO';
    }

    /** Un FCL debe declarar al menos un contenedor antes de zarpar. */
    public function isMissingContainers(): bool
    {
        if ($this->tipo_carga !== 'FCL') {
            return false;
        }

        $count = $this->relationLoaded('containers') ? $this->containers->count() : $this->containers()->count();

        return $count === 0;
    }

    public function hasTarja(): bool
    {
        return $this->fecha_tarja !== null || $this->fecha_ingreso_almacen !== null;
    }

    /** Enlace a mapa si Operaciones registro coordenadas. */
    public function locationMapUrl(): ?string
    {
        if ($this->ubicacion_latitud === null || $this->ubicacion_longitud === null) {
            return null;
        }

        return sprintf(
            'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=8/%1$s/%2$s',
            $this->ubicacion_latitud,
            $this->ubicacion_longitud
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Comercial que vendio y creo la RO. */
    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }

    /** Customer Service asignado: unico contacto con el cliente. */
    public function customerService(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** Operador duenio de los datos tecnicos. */
    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    /** La RO sigue en su primer estado: el Comercial aun puede corregirla. */
    public function isInCreationStage(): bool
    {
        return $this->estado_actual === null || $this->estado_actual === static::TRACKING_STATES[0];
    }

    /**
     * Copia la condicion de credito maestra del cliente al embarque.
     * Se usa al crear la RO y cuando el credito no fue forzado por el Admin.
     */
    public function inheritCreditFrom(Client $client): void
    {
        $this->condicion_credito = $client->condicion_credito ?? Client::CREDIT_CASH;
        $this->dias_credito = $this->condicion_credito === Client::CREDIT_TERM ? $client->dias_credito : null;
        $this->credito_forzado = false;
    }

    /**
     * Aplica una condicion definida por el Admin. Si coincide con la del
     * cliente, el embarque vuelve a quedar como "heredado".
     */
    public function overrideCredit(Client $client, string $condition, ?int $days): void
    {
        $days = $condition === Client::CREDIT_TERM ? $days : null;

        $this->condicion_credito = $condition;
        $this->dias_credito = $days;
        $this->credito_forzado = $condition !== ($client->condicion_credito ?? Client::CREDIT_CASH)
            || ($condition === Client::CREDIT_TERM && (int) $days !== (int) $client->dias_credito);
    }

    public function creditLabel(): string
    {
        return Client::creditLabelFor($this->condicion_credito, $this->dias_credito);
    }

    /**
     * Embarques vinculados a un usuario: como responsable por rol
     * (comercial, customer u operador) o por asignacion con alertas activas.
     */
    public function scopeLinkedTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $linked) use ($user) {
            $linked
                ->where('shipments.comercial_id', $user->id)
                ->orWhere('shipments.customer_id', $user->id)
                ->orWhere('shipments.operador_id', $user->id)
                ->orWhereHas('userAssignments', fn (Builder $assignment) => $assignment
                    ->where('user_id', $user->id)
                    ->where('alerts_enabled', true));
        });
    }

    /** Etiqueta del vinculo del usuario con el embarque (para el dashboard). */
    public function linkLabelFor(User $user): string
    {
        return match (true) {
            $this->customer_id === $user->id => 'Customer',
            $this->operador_id === $user->id => 'Operador',
            $this->comercial_id === $user->id => 'Comercial',
            default => ($this->userAssignments->firstWhere('user_id', $user->id)?->assignment_type
                === ShipmentUserAssignment::TYPE_RESPONSABLE) ? 'Responsable' : 'Seguimiento',
        };
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('fecha');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest();
    }

    public static function trackingStateIndex(?string $state): ?int
    {
        $index = array_search($state, static::TRACKING_STATES, true);

        return $index === false ? null : $index;
    }

    public function currentTrackingStateIndex(): int
    {
        return static::trackingStateIndex($this->estado_actual) ?? 0;
    }

    public function canAdvanceToTrackingState(string $state): bool
    {
        $targetIndex = static::trackingStateIndex($state);

        return $targetIndex !== null && $targetIndex > $this->currentTrackingStateIndex();
    }

    /** Pre-alertas de llegada (la primera es la mas reciente: el estado actual). */
    public function preAlerts(): HasMany
    {
        return $this->hasMany(ShipmentPreAlert::class)->latest('id');
    }

    public function provisions(): HasMany
    {
        return $this->hasMany(Provision::class)->latest('fecha');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest();
    }

    public function userPreferences(): HasMany
    {
        return $this->hasMany(UserShipmentPreference::class);
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(ShipmentUserAssignment::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shipment_user_assignments')
            ->withPivot(['assignment_type', 'alerts_enabled'])
            ->withTimestamps();
    }

    public function endorsementResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_endoso_id');
    }

    public function canRevealTrackingCodeTo(?User $user): bool
    {
        return $user !== null && $user->isInternal();
    }

    public static function generateTrackingCode(): string
    {
        do {
            $code = 'FX-'.Str::upper(Str::random(8));
        } while (static::query()->where('tracking_code', $code)->exists());

        return $code;
    }

    public function heroTransportIcon(): string
    {
        return static::cargoTypeVisual($this->tipo_carga)['hero_icon'];
    }

    public function routeTransportIcon(): string
    {
        $visual = static::trackingStateVisual($this->estado_actual, $this->tipo_carga);

        return $visual['route_icon'] ?? static::cargoTypeVisual($this->tipo_carga)['route_icon'];
    }

    public function eventStateIcon(?string $state): string
    {
        return static::trackingStateVisual($state, $this->tipo_carga)['event_icon'];
    }

    public function documentChecklist(): array
    {
        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        $categories = [
            [
                'key' => Document::CATEGORY_OPERATIVO,
                'items' => [
                    // El borrador lo valida el Customer con el cliente antes de que la naviera emita el BL.
                    $this->checklistItem('Borrador de BL', Document::CATEGORY_OPERATIVO, self::CHECKLIST_PRIORITY_REQUIRED, $this->isMaritimeCargo() && ! $this->isAtOrPastTrackingState('ATD'), 'Borrador revisado con el cliente antes de emitir el BL.'),
                    $this->checklistItem('BL', Document::CATEGORY_OPERATIVO, self::CHECKLIST_PRIORITY_REQUIRED, true, 'Documento principal del embarque.'),
                    $this->checklistItem('Commercial Invoice', Document::CATEGORY_OPERATIVO, self::CHECKLIST_PRIORITY_REQUIRED, true, 'Sustento comercial minimo para validar la carga.'),
                    $this->checklistItem('Packing List', Document::CATEGORY_OPERATIVO, self::CHECKLIST_PRIORITY_RECOMMENDED, true, 'Ayuda a validar bultos, contenido y cubicaje.'),
                    $this->checklistItem('Foto de carga', Document::CATEGORY_OPERATIVO, self::CHECKLIST_PRIORITY_RECOMMENDED, $this->isAtOrPastTrackingState('Booking Confirmado'), 'Evidencia visual del embarque o despacho.'),
                    $this->checklistItem('Certificado', Document::CATEGORY_OPERATIVO, self::CHECKLIST_PRIORITY_RECOMMENDED, $this->isMaritimeCargo(), 'Respaldo adicional cuando la operacion o el cliente lo requieran.'),
                ],
            ],
            [
                'key' => Document::CATEGORY_ENDOSO,
                'items' => [
                    $this->checklistItem('BL Endosado', Document::CATEGORY_ENDOSO, self::CHECKLIST_PRIORITY_REQUIRED, $this->requiresEndorsementChecklist(), 'Sustento minimo para liberar y cerrar el endoso.'),
                    $this->checklistItem('Carta de Endoso', Document::CATEGORY_ENDOSO, self::CHECKLIST_PRIORITY_RECOMMENDED, $this->requiresEndorsementChecklist(), 'Carta o instruccion formal del proceso de endoso.'),
                    $this->checklistItem('Instruccion de Endoso', Document::CATEGORY_ENDOSO, self::CHECKLIST_PRIORITY_RECOMMENDED, $this->requiresEndorsementChecklist(), 'Detalle operativo para ejecutar el endoso sin observaciones.'),
                    $this->checklistItem('Cargo de Endoso', Document::CATEGORY_ENDOSO, self::CHECKLIST_PRIORITY_RECOMMENDED, $this->estado_endoso === 'completado', 'Evidencia de cierre o recepcion del endoso.'),
                ],
            ],
            [
                'key' => Document::CATEGORY_ADUANAS,
                'items' => [
                    $this->checklistItem('DAM', Document::CATEGORY_ADUANAS, self::CHECKLIST_PRIORITY_REQUIRED, $this->isAtOrPastTrackingState('Transmision Aduanas'), 'Declaracion aduanera o documento equivalente.'),
                    $this->checklistItem('Transmision Aduanas', Document::CATEGORY_ADUANAS, self::CHECKLIST_PRIORITY_RECOMMENDED, $this->isAtOrPastTrackingState('Transmision Aduanas'), 'Constancia o soporte de transmision a aduanas.'),
                    $this->checklistItem('Levante', Document::CATEGORY_ADUANAS, self::CHECKLIST_PRIORITY_RECOMMENDED, $this->isAtOrPastTrackingState('Arribo a puerto'), 'Sustenta liberacion aduanera de la carga.'),
                    $this->checklistItem('Sustento Aduanas', Document::CATEGORY_ADUANAS, self::CHECKLIST_PRIORITY_RECOMMENDED, $this->isAtOrPastTrackingState('Descarga'), 'Archivo de respaldo para observaciones o regularizaciones.'),
                ],
            ],
            [
                'key' => Document::CATEGORY_FACTURACION,
                'label' => 'Facturación',
                'description' => 'Sustentos del cierre comercial y cobranza.',
                'tone' => 'emerald',
                'items' => [
                    $this->checklistItem('Sustento de cobro', Document::CATEGORY_FACTURACION, self::CHECKLIST_PRIORITY_REQUIRED, $this->isAtOrPastTrackingState('Ingreso a almacen'), 'Respaldo para emitir cobro al cliente.'),
                    $this->checklistItem('Factura cliente', Document::CATEGORY_FACTURACION, self::CHECKLIST_PRIORITY_RECOMMENDED, $this->isAtOrPastTrackingState('Entregado'), 'Copia o sustento final de la factura al cliente.'),
                    $this->checklistItem('Comprobante', Document::CATEGORY_FACTURACION, self::CHECKLIST_PRIORITY_RECOMMENDED, $this->isAtOrPastTrackingState('Finalizado'), 'Comprobante final para cierre documental de la operacion.'),
                ],
            ],
        ];

        return collect($categories)->map(function (array $category) use ($documents) {
            $visuals = static::DOCUMENT_CHECKLIST_VISUALS[$category['key']];
            $items = collect($category['items'])
                ->filter(fn (array $item) => $item['active'])
                ->map(function (array $item) use ($documents) {
                    // Ultima version subida de ese tipo de documento.
                    $latest = $documents
                        ->filter(fn (Document $document) => mb_strtolower($document->tipo_documento) === mb_strtolower($item['type']))
                        ->sortByDesc(fn (Document $document) => [$document->created_at?->getTimestamp() ?? 0, $document->id])
                        ->first();

                    $item['document'] = $latest;
                    $item['uploaded'] = $latest !== null;
                    $item['validation'] = $latest?->estado_validacion;
                    // Un documento que requiere validacion solo cuenta cuando el Customer lo aprueba.
                    $item['fulfilled'] = $latest !== null && (! $latest->needsValidation() || $latest->isApproved());

                    return $item;
                })
                ->values();

            $requiredItems = $items->where('priority', self::CHECKLIST_PRIORITY_REQUIRED)->values();
            $recommendedItems = $items->where('priority', self::CHECKLIST_PRIORITY_RECOMMENDED)->values();
            $missingRequired = $requiredItems->where('fulfilled', false)->values();
            $missingRecommended = $recommendedItems->where('fulfilled', false)->values();

            return [
                'key' => $category['key'],
                'label' => $visuals['label'],
                'description' => $visuals['description'],
                'tone' => $visuals['tone'],
                'highlight' => $visuals['highlight'],
                'badge' => $visuals['badge'],
                'documents' => $documents->where('categoria', $category['key'])->values(),
                'count' => $documents->where('categoria', $category['key'])->count(),
                'items' => $items,
                'required_items' => $requiredItems,
                'recommended_items' => $recommendedItems,
                'missing_required' => $missingRequired,
                'missing_recommended' => $missingRecommended,
                'missing_items' => $missingRequired->concat($missingRecommended)->values(),
                'active_items_count' => $items->count(),
                'completed_items_count' => $items->where('fulfilled', true)->count(),
                'required_completion' => $requiredItems->count() === 0 || $missingRequired->isEmpty(),
            ];
        })->values()->all();
    }

    public function originCountryCode(): ?string
    {
        return static::countryCodeForCountryField($this->origen);
    }

    public function destinationCountryCode(): ?string
    {
        return static::countryCodeForCountryField($this->destino_final);
    }

    public static function cargoTypeVisual(?string $cargoType): array
    {
        $type = Str::upper((string) $cargoType);

        return static::CARGO_TYPE_VISUALS[$type] ?? [
            'hero_icon' => 'truck',
            'route_icon' => 'truck',
        ];
    }

    public static function countryOptions(): array
    {
        return static::COUNTRY_OPTIONS;
    }

    public static function trackingStateVisual(?string $state, ?string $cargoType): array
    {
        $resolved = static::TRACKING_STATE_VISUALS[(string) $state] ?? static::inferTrackingStateVisual($state);
        $type = Str::upper((string) $cargoType);

        if (isset($resolved['cargo_overrides'][$type])) {
            $resolved = array_merge($resolved, $resolved['cargo_overrides'][$type]);
        }

        unset($resolved['cargo_overrides']);

        return array_merge(static::TRACKING_STATE_DEFAULT_VISUAL, $resolved);
    }

    public static function countryCodeFor(?string $place): ?string
    {
        return static::countryCodeForCountryField($place);
    }

    public static function countryCodeForCountryField(?string $value): ?string
    {
        $normalized = static::normalizePlace($value);

        if ($normalized === '') {
            return null;
        }

        if (array_key_exists($normalized, static::COUNTRY_CODES)) {
            return static::COUNTRY_CODES[$normalized];
        }

        foreach (preg_split('/[,;|]/', (string) $value) ?: [] as $segment) {
            $normalizedSegment = static::normalizePlace($segment);

            if ($normalizedSegment !== '' && array_key_exists($normalizedSegment, static::COUNTRY_CODES)) {
                return static::COUNTRY_CODES[$normalizedSegment];
            }
        }

        foreach (static::COUNTRY_CODES as $country => $code) {
            $normalizedCountry = static::normalizePlace($country);

            if (
                str_contains($normalized, ',') &&
                str_contains($normalized, $normalizedCountry)
            ) {
                return $code;
            }
        }

        return null;
    }

    private static function inferTrackingStateVisual(?string $state): array
    {
        $normalized = static::normalizeTrackingState($state);

        return match (true) {
            str_contains($normalized, 'aduana') => ['event_icon' => 'file-check', 'route_icon' => 'file-check'],
            str_contains($normalized, 'almacen') => ['event_icon' => 'warehouse', 'route_icon' => 'warehouse'],
            str_contains($normalized, 'retiro') => ['event_icon' => 'truck', 'route_icon' => 'truck'],
            str_contains($normalized, 'descarga') => ['event_icon' => 'container', 'route_icon' => 'container'],
            str_contains($normalized, 'arribo') || str_contains($normalized, 'puerto') => ['event_icon' => 'anchor', 'route_icon' => 'anchor'],
            str_contains($normalized, 'atd') || str_contains($normalized, 'transito') => ['event_icon' => 'ship', 'route_icon' => 'ship'],
            str_contains($normalized, 'eta') => ['event_icon' => 'clock-3', 'route_icon' => 'clock-3'],
            str_contains($normalized, 'entrega') || str_contains($normalized, 'finalizado') => ['event_icon' => 'package-check', 'route_icon' => 'package-check'],
            default => static::TRACKING_STATE_DEFAULT_VISUAL,
        };
    }

    private static function normalizeTrackingState(?string $state): string
    {
        return Str::of(Str::ascii((string) $state))
            ->lower()
            ->value();
    }

    private static function normalizePlace(?string $place): string
    {
        return Str::of(Str::ascii((string) $place))
            ->lower()
            ->replace(['.', ',', '-', '_', '/'], ' ')
            ->squish()
            ->value();
    }

    private function checklistItem(string $type, string $category, string $priority, bool $active, string $hint): array
    {
        return [
            'type' => $type,
            'category' => $category,
            'label' => $type,
            'priority' => $priority,
            'priority_label' => $priority === self::CHECKLIST_PRIORITY_REQUIRED ? 'Obligatorio' : 'Recomendado',
            'active' => $active,
            'hint' => $hint,
            'fulfilled' => false,
        ];
    }

    private function isAtOrPastTrackingState(string $state): bool
    {
        $targetIndex = static::trackingStateIndex($state);

        return $targetIndex !== null && $this->currentTrackingStateIndex() >= $targetIndex;
    }

    private function isMaritimeCargo(): bool
    {
        return in_array(Str::upper((string) $this->tipo_carga), ['FCL', 'LCL'], true);
    }

    private function requiresEndorsementChecklist(): bool
    {
        return $this->estado_endoso !== 'pendiente' || filled($this->fecha_endoso);
    }
}
