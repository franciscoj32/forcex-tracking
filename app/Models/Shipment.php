<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Shipment extends Model
{
    use HasFactory;

    protected static ?bool $trackingCodeColumnExists = null;

    private const TRACKING_STATE_DEFAULT_VISUAL = [
        'event_icon' => 'map-pinned',
        'route_icon' => null,
    ];

    public const TIPOS_CARGA = ['FCL', 'LCL', 'AEREO'];

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
        'nro_orden_trabajo',
        'routing_order',
        'bl',
        'tipo_carga',
        'origen',
        'puerto_carga',
        'puerto_descarga',
        'destino_final',
        'naviera',
        'consignatario',
        'peso',
        'volumen',
        'fecha_etd',
        'fecha_eta',
        'fecha_descarga',
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
            if (static::hasTrackingCodeColumn() && blank($shipment->tracking_code)) {
                $shipment->tracking_code = static::generateTrackingCode();
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
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
        if (! $user || ! static::hasTrackingCodeColumn()) {
            return false;
        }

        return $user->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL]);
    }

    public static function generateTrackingCode(): string
    {
        do {
            $code = 'FX-'.Str::upper(Str::random(8));
        } while (static::query()->where('tracking_code', $code)->exists());

        return $code;
    }

    public static function hasTrackingCodeColumn(): bool
    {
        return static::$trackingCodeColumnExists ??= Schema::hasColumn('shipments', 'tracking_code');
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
        $documentTypes = $documents->pluck('tipo_documento');

        $categories = [
            [
                'key' => Document::CATEGORY_OPERATIVO,
                'items' => [
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

        return collect($categories)->map(function (array $category) use ($documents, $documentTypes) {
            $visuals = static::DOCUMENT_CHECKLIST_VISUALS[$category['key']];
            $items = collect($category['items'])
                ->filter(fn (array $item) => $item['active'])
                ->map(function (array $item) use ($documentTypes) {
                    $item['fulfilled'] = $documentTypes->contains($item['type']);

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
        return static::countryCodeFor($this->origen);
    }

    public function destinationCountryCode(): ?string
    {
        return static::countryCodeFor($this->destino_final);
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
        $normalized = static::normalizePlace($place);

        foreach (static::COUNTRY_CODES as $country => $code) {
            if (str_contains($normalized, static::normalizePlace($country))) {
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
