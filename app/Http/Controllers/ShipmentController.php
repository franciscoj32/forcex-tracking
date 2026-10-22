<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Document;
use App\Models\RateConcept;
use App\Models\Shipment;
use App\Models\ShipmentRate;
use App\Models\ShipmentEvent;
use App\Models\ShipmentUserAssignment;
use App\Models\User;
use App\Models\UserShipmentPreference;
use App\Support\Exports\ShipmentExportRows;
use App\Support\Exports\XlsxExport;
use App\Support\ShipmentFieldAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    /** Campos que no se asignan en bloque al modelo. */
    private const NON_ATTRIBUTE_FIELDS = ['responsable_users', 'tracking_users', 'condicion_credito', 'dias_credito', 'rates'];

    /**
     * Vistas rapidas del listado. Cada una responde a una pregunta de trabajo
     * y su numero reemplaza a las tarjetas de indicadores.
     */
    public const INDEX_VIEWS = [
        'activos' => 'Activos',
        'retrasados' => 'Retrasados',
        'arribos' => 'Arriban en 7 días',
        'validar' => 'Fecha por validar',
        'finalizados' => 'Finalizados',
        'todos' => 'Todos',
    ];

    public function index(): View
    {
        $user = auth()->user();
        $filters = $this->indexFilters(request());

        $base = fn () => $this->buildIndexQuery($user, $filters['q'], $filters['client_id'], 'todos', $filters['tipo'], $filters['mios']);

        $counts = collect(array_keys(self::INDEX_VIEWS))
            ->mapWithKeys(fn (string $view) => [$view => $this->applyIndexView($base(), $view)->count()]);

        $query = $this->applyIndexView($base(), $filters['vista'])
            ->withCount('pendingDateChanges')
            ->with(['customerService:id,name', 'operador:id,name'])
            ->orderByDesc('is_pinned_for_user');

        // Retrasados y por arribar se leen por urgencia; el resto por ultima actividad.
        if (in_array($filters['vista'], ['retrasados', 'arribos'], true)) {
            $query->orderBy('shipments.fecha_eta');
        }

        $query->latest('shipments.updated_at');

        return view('shipments.index', [
            'shipments' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'views' => self::INDEX_VIEWS,
            'counts' => $counts,
            'clients' => $user->isInternal()
                ? Client::orderBy('razon_social')->get(['id', 'razon_social'])
                : collect(),
        ]);
    }

    /** Filtros del listado y del Excel (acepta el parametro antiguo `lifecycle`). */
    private function indexFilters(Request $request): array
    {
        $user = $request->user();
        $legacy = ['active' => 'activos', 'finished' => 'finalizados', 'all' => 'todos'];
        $view = (string) $request->query('vista', $legacy[$request->query('lifecycle')] ?? 'activos');

        return [
            'q' => trim((string) $request->query('q', '')),
            'vista' => array_key_exists($view, self::INDEX_VIEWS) ? $view : 'activos',
            'client_id' => $user->isInternal() ? (string) $request->query('client_id', '') : '',
            'tipo' => in_array($request->query('tipo'), ['FCL', 'LCL', 'AEREO'], true) ? (string) $request->query('tipo') : '',
            'mios' => $request->boolean('mios'),
        ];
    }

    private function applyIndexView($query, string $view)
    {
        return match ($view) {
            'activos' => $query->where('shipments.estado_actual', '!=', 'Finalizado'),
            'retrasados' => $query->delayed(),
            'arribos' => $query
                ->whereBetween('shipments.fecha_eta', [now()->startOfDay()->toDateString(), now()->addDays(7)->toDateString()])
                ->whereIn('shipments.estado_actual', Shipment::statesBeforeArrival()),
            'validar' => $query->whereHas('pendingDateChanges'),
            'finalizados' => $query->where('shipments.estado_actual', 'Finalizado'),
            default => $query,
        };
    }

    /** Excel real (.xlsx) del listado con los filtros aplicados. */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $applied = $this->indexFilters($request);

        $shipments = $this->applyIndexView(
            $this->buildIndexQuery($user, $applied['q'], $applied['client_id'], 'todos', $applied['tipo'], $applied['mios']),
            $applied['vista']
        )
            ->with(['comercial:id,name', 'customerService:id,name', 'operador:id,name', 'containers:id,shipment_id,numero'])
            ->orderByDesc('is_pinned_for_user')
            ->latest('shipments.updated_at')
            ->get();

        $filters = [
            'Vista' => self::INDEX_VIEWS[$applied['vista']],
            'Busqueda' => $applied['q'] !== '' ? $applied['q'] : 'Todas',
            'Cliente' => blank($applied['client_id'])
                ? 'Todos'
                : (string) Client::query()->whereKey($applied['client_id'])->value('razon_social'),
            'Tipo' => $applied['tipo'] !== '' ? $applied['tipo'] : 'Todos',
            'Responsable' => $applied['mios'] ? $user->name : 'Todos',
        ];

        return (new XlsxExport('Embarques'))
            ->sheet('Embarques', ShipmentExportRows::operationsColumns(), ShipmentExportRows::operationsRows($shipments), $filters)
            ->download('embarques_'.now()->format('Ymd_His').'.xlsx');
    }

    public function create(): View
    {
        Gate::authorize('create', Shipment::class);

        $shipment = new Shipment([
            'estado_actual' => Shipment::TRACKING_STATES[0],
            'estado_endoso' => 'pendiente',
        ]);

        return view('shipments.create', $this->formData(
            $shipment,
            ShipmentFieldAccess::editableOnCreate(auth()->user())
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Shipment::class);

        $user = $request->user();
        $editable = ShipmentFieldAccess::editableOnCreate($user);
        $this->discardEmptyRateRows($request);
        $validated = $request->validate($this->rulesFor($editable, $request), [], $this->attributeLabels());
        $client = Client::findOrFail($validated['client_id']);

        $shipment = DB::transaction(function () use ($validated, $editable, $client, $user, $request) {
            $shipment = new Shipment($this->shipmentAttributes($validated));

            // Toda RO nace en el primer estado del flujo.
            $shipment->estado_actual = Shipment::TRACKING_STATES[0];
            $shipment->estado_endoso = $shipment->estado_endoso ?: 'pendiente';

            // El comercial que registra la RO queda como duenio de la venta.
            if ($user->isComercial()) {
                $shipment->comercial_id = $user->id;
            }

            // Tipo de cambio congelado: el de referencia del dia, salvo que el Admin lo indique.
            $shipment->tipo_cambio = filled($validated['tipo_cambio'] ?? null)
                ? $validated['tipo_cambio']
                : AppSetting::referenceExchangeRate();

            $this->applyCredit($shipment, $client, $validated);
            $shipment->save();

            if (in_array('rates', $editable, true)) {
                $this->syncRates($shipment, $validated['rates'] ?? []);
            }

            if (in_array('responsable_users', $editable, true)) {
                $this->syncAssignments($shipment, $request);
            }

            // Primer hito (visible al cliente con su texto estandar).
            ShipmentEvent::recordState($shipment, $shipment->estado_actual, now(), 'Embarque creado en el sistema.', $user->id);

            return $shipment;
        });

        return redirect()->route('embarques.show', $shipment)->with('status', 'Routing Order creada correctamente.');
    }

    public function show(Shipment $shipment): View
    {
        Gate::authorize('view', $shipment);

        $activeTab = request('tab', 'datos');

        $canSeeFinance = auth()->user()->can('viewRates', $shipment) || auth()->user()->can('view-provisions');

        if (! in_array($activeTab, ['datos', 'tracking', 'documentos', 'finanzas'], true)
            || ($activeTab === 'finanzas' && ! $canSeeFinance)) {
            $activeTab = 'datos';
        }

        $shipment->load('client');

        $timelineEvents = $shipment->events()
            ->with('user:id,name')
            ->orderBy('fecha')
            ->get([
                'id',
                'shipment_id',
                'estado',
                'tipo',
                'visible_cliente',
                'fecha',
                'descripcion',
                'user_id',
            ]);
        // Encabezado y "Requiere atencion" se muestran en todas las pestanas.
        $shipment->loadMissing([
            'containers',
            'documents.user:id,name',
            'documents.validatedBy:id,name',
            'pendingDateChanges.changedBy:id,name',
            'comercial:id,name,email',
            'customerService:id,name,email',
            'operador:id,name,email',
        ]);

        if ($activeTab === 'datos') {
            $shipment->loadMissing([
                'endorsementResponsible:id,name',
                'userAssignments.user:id,name',
                'cutOffConfirmedBy:id,name',
            ]);
        }

        if ($activeTab === 'tracking') {
            $shipment->loadMissing([
                'notificationLogs' => fn ($query) => $query->limit(15),
            ]);
        }

        if ($activeTab === 'finanzas') {
            $shipment->loadMissing([
                'comercial:id,name',
                'rates.concept:id,nombre',
                'provisions.supplier:id,razon_social',
                'provisions.concept:id,nombre',
                'invoices',
            ]);
        }

        return view('shipments.show', [
            'shipment' => $shipment,
            'activeTab' => $activeTab,
            'timelineEvents' => $timelineEvents,
            'trackingStates' => Shipment::TRACKING_STATES,
            'endorsementStatuses' => Shipment::ENDORSEMENT_STATUSES,
            'documentCategories' => Document::CATEGORIES,
            'documentTypeSuggestions' => Document::TYPE_SUGGESTIONS,
            'canSeeFinance' => $canSeeFinance,
        ]);
    }

    public function edit(Shipment $shipment): View
    {
        Gate::authorize('update', $shipment);

        $shipment->loadMissing(['userAssignments', 'client', 'rates.concept:id,nombre']);

        return view('shipments.edit', $this->formData(
            $shipment,
            ShipmentFieldAccess::editableOnUpdate(auth()->user(), $shipment)
        ));
    }

    public function update(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('update', $shipment);

        $user = $request->user();
        $editable = ShipmentFieldAccess::editableOnUpdate($user, $shipment);
        $this->discardEmptyRateRows($request);
        $validated = $request->validate($this->rulesFor($editable, $request, $shipment), [], $this->attributeLabels());

        $previousState = $shipment->estado_actual;
        $shipment->loadMissing(['userAssignments', 'rates']);
        $canSyncAssignments = in_array('responsable_users', $editable, true);
        $hadAssignmentChanges = $canSyncAssignments && $this->shipmentAssignmentsWillChange($shipment, $request);
        $canSyncRates = in_array('rates', $editable, true);
        $hadRateChanges = $canSyncRates && $this->ratesWillChange($shipment, $validated['rates'] ?? []);

        DB::transaction(function () use ($shipment, $validated, $request, $user, $previousState, $canSyncAssignments, $hadAssignmentChanges, $canSyncRates, $hadRateChanges) {
            $shipment->fill($this->shipmentAttributes($validated));

            // Si el Admin borra el tipo de cambio, se vuelve al de referencia.
            if (array_key_exists('tipo_cambio', $validated) && blank($validated['tipo_cambio'])) {
                $shipment->tipo_cambio = AppSetting::referenceExchangeRate();
            }

            $client = $shipment->isDirty('client_id')
                ? Client::findOrFail($shipment->client_id)
                : $shipment->client;

            $this->applyCredit($shipment, $client, $validated);

            $fieldChanges = array_keys(Arr::except($shipment->getDirty(), ['updated_at']));

            if ($hadRateChanges) {
                $fieldChanges[] = 'rates';
            }

            $shipment->save();

            if ($canSyncAssignments) {
                $this->syncAssignments($shipment, $request);
            }

            if ($hadRateChanges) {
                $this->syncRates($shipment, $validated['rates'] ?? []);
            }

            // Cambio de estado: hito visible al cliente.
            if ($previousState !== $shipment->estado_actual) {
                ShipmentEvent::recordState($shipment, $shipment->estado_actual, now(), 'Estado actualizado desde la ficha del embarque.', $user->id);
            }

            // Resto de cambios: bitacora interna, nunca visible al cliente.
            $auditDescription = $this->buildShipmentAuditDescription(
                array_values(array_diff($fieldChanges, ['estado_actual'])),
                $hadAssignmentChanges
            );

            if ($auditDescription !== null) {
                ShipmentEvent::recordAudit($shipment, $auditDescription, $user->id);
            }
        });

        return redirect()->route('embarques.show', $shipment)->with('status', 'Embarque actualizado correctamente.');
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        Gate::authorize('delete', $shipment);

        $shipment->load(['documents', 'invoices']);

        foreach ($shipment->documents as $document) {
            if (Storage::disk('private')->exists($document->ruta)) {
                Storage::disk('private')->delete($document->ruta);
            }
        }

        foreach ($shipment->invoices as $invoice) {
            if ($invoice->archivo_pdf && Storage::disk('private')->exists($invoice->archivo_pdf)) {
                Storage::disk('private')->delete($invoice->archivo_pdf);
            }
        }

        $shipment->delete();

        return redirect()->route('embarques.index')->with('status', 'Embarque eliminado correctamente.');
    }

    public function togglePin(Shipment $shipment): RedirectResponse
    {
        Gate::authorize('view', $shipment);

        $preference = UserShipmentPreference::firstOrNew([
            'user_id' => auth()->id(),
            'shipment_id' => $shipment->id,
        ]);

        $preference->is_pinned = ! ($preference->exists ? $preference->is_pinned : false);
        $preference->save();

        return back()->with(
            'status',
            $preference->is_pinned
                ? 'Embarque destacado y fijado en la parte superior.'
                : 'Embarque quitado de destacados.'
        );
    }

    /**
     * Datos compartidos por los formularios de creacion y edicion.
     * "editableFields" le dice a la vista que campos bloquear.
     */
    private function formData(Shipment $shipment, array $editableFields): array
    {
        $activeUsers = fn (array $roles) => User::query()
            ->whereIn('role', $roles)
            ->where('estado', User::ESTADO_ACTIVO)
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'role']);

        // Solo clientes aprobados y activos; en edicion se conserva el actual.
        $clients = Client::query()
            ->operable()
            ->when($shipment->client_id, fn ($query) => $query->orWhere('id', $shipment->client_id))
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'ruc', 'condicion_credito', 'dias_credito']);

        return [
            'shipment' => $shipment,
            'editableFields' => $editableFields,
            'clients' => $clients,
            'customerUsers' => $activeUsers([User::ROLE_CUSTOMER]),
            'operatorUsers' => $activeUsers([User::ROLE_OPERACIONES]),
            'commercialUsers' => $activeUsers([User::ROLE_COMERCIAL, User::ROLE_ADMIN]),
            'endorsementUsers' => $activeUsers(User::INTERNAL_ROLES),
            'assignmentUsers' => $activeUsers(User::INTERNAL_ROLES),
            'countries' => Shipment::countryOptions(),
            'trackingStates' => Shipment::TRACKING_STATES,
            'endorsementStatuses' => Shipment::ENDORSEMENT_STATUSES,
            'types' => Shipment::TIPOS_CARGA,
            'incoterms' => Shipment::INCOTERMS,
            'creditConditions' => Client::CREDIT_CONDITIONS,
            // Conceptos activos y los ya usados por la RO (aunque luego se desactivaran).
            'rateConcepts' => RateConcept::query()
                ->where('activo', true)
                ->when($shipment->exists, fn ($query) => $query->orWhereIn('id', $shipment->rates()->pluck('rate_concept_id')))
                ->ordered()
                ->get(['id', 'nombre', 'activo']),
            'currencies' => ShipmentRate::CURRENCIES,
            'referenceExchangeRate' => AppSetting::referenceExchangeRate(),
        ];
    }

    /**
     * Reglas de validacion limitadas a los campos que el rol puede editar.
     * Los campos no permitidos ni siquiera se validan: se ignoran.
     */
    private function rulesFor(array $editable, Request $request, ?Shipment $shipment = null): array
    {
        $clientId = in_array('client_id', $editable, true)
            ? $request->integer('client_id')
            : (int) $shipment?->client_id;

        $activeUserWithRole = fn (array $roles) => Rule::exists('users', 'id')->where(fn ($query) => $query
            ->whereIn('role', $roles)
            ->where('estado', User::ESTADO_ACTIVO));

        $rules = [
            'client_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($shipment) {
                    if ($shipment && (int) $value === (int) $shipment->client_id) {
                        return;
                    }

                    $client = Client::find($value);

                    if (! $client) {
                        $fail('El cliente seleccionado no existe.');
                    } elseif (! $client->canOperate()) {
                        $fail('El cliente debe estar aprobado por el Administrador y activo para emitir una RO.');
                    }
                },
            ],
            'nro_orden_trabajo' => ['required', 'string', 'max:100', Rule::unique('shipments', 'nro_orden_trabajo')->ignore($shipment)],
            'routing_order' => [
                'required',
                'string',
                'max:100',
                Rule::unique('shipments', 'routing_order')
                    ->where(fn ($query) => $query->where('client_id', $clientId))
                    ->ignore($shipment),
            ],
            'bl' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('shipments', 'bl')
                    ->where(fn ($query) => $query->where('client_id', $clientId))
                    ->ignore($shipment),
            ],
            'tipo_carga' => ['required', Rule::in(Shipment::TIPOS_CARGA)],
            'incoterm' => ['required', Rule::in(Shipment::INCOTERMS)],
            'origen' => ['required', 'string', 'max:255'],
            'puerto_carga' => ['required', 'string', 'max:255'],
            'puerto_descarga' => ['required', 'string', 'max:255'],
            'destino_final' => ['required', 'string', 'max:255'],
            'naviera' => ['nullable', 'string', 'max:255'],
            'nave' => ['nullable', 'string', 'max:120'],
            'viaje' => ['nullable', 'string', 'max:60'],
            'agente_origen' => ['nullable', 'string', 'max:255'],
            'consignatario' => ['nullable', 'string', 'max:255'],
            'peso' => ['nullable', 'numeric', 'min:0'],
            'volumen' => ['nullable', 'numeric', 'min:0'],
            'fecha_cut_off' => ['nullable', 'date'],
            'fecha_etd' => ['nullable', 'date'],
            'fecha_eta' => ['nullable', 'date', Rule::when($request->filled('fecha_etd'), ['after_or_equal:fecha_etd'])],
            'fecha_descarga' => ['nullable', 'date'],
            'dias_libres_demurrage' => ['nullable', 'integer', 'min:0', 'max:365'],
            'dias_libres_detention' => ['nullable', 'integer', 'min:0', 'max:365'],
            'dias_libres_almacen' => ['nullable', 'integer', 'min:0', 'max:365'],
            'ubicacion_actual' => ['nullable', 'string', 'max:255'],
            'ubicacion_latitud' => ['nullable', 'required_with:ubicacion_longitud', 'numeric', 'between:-90,90'],
            'ubicacion_longitud' => ['nullable', 'required_with:ubicacion_latitud', 'numeric', 'between:-180,180'],
            'almacen_direccionamiento' => ['nullable', 'string', 'max:255'],
            'fecha_tarja' => ['nullable', 'date'],
            'bultos_tarjados' => ['nullable', 'integer', 'min:0'],
            'peso_tarjado_kg' => ['nullable', 'numeric', 'min:0'],
            'fecha_ingreso_almacen' => ['nullable', 'date'],
            'observacion_tarja' => ['nullable', 'string'],
            'estado_actual' => [
                'required',
                Rule::in(Shipment::TRACKING_STATES),
                function (string $attribute, mixed $value, \Closure $fail) use ($shipment) {
                    if (! $shipment || ! is_string($value) || $value === $shipment->estado_actual) {
                        return;
                    }

                    if (! $shipment->canAdvanceToTrackingState($value)) {
                        $fail('El estado actual solo puede avanzar en el flujo del embarque.');
                    }
                },
            ],
            'estado_endoso' => ['required', Rule::in(Shipment::ENDORSEMENT_STATUSES)],
            'fecha_endoso' => ['nullable', 'date'],
            'responsable_endoso_id' => ['nullable', $activeUserWithRole(User::INTERNAL_ROLES)],
            'observacion_endoso' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.rate_concept_id' => ['required', 'integer', Rule::exists('rate_concepts', 'id')],
            'rates.*.monto' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'rates.*.moneda' => ['required', Rule::in(ShipmentRate::CURRENCIES)],
            'rates.*.observacion' => ['nullable', 'string', 'max:255'],
            'tipo_cambio' => ['nullable', 'numeric', 'between:1,10'],
            'customer_id' => ['required', $activeUserWithRole([User::ROLE_CUSTOMER])],
            'operador_id' => ['nullable', $activeUserWithRole([User::ROLE_OPERACIONES])],
            'comercial_id' => ['nullable', $activeUserWithRole([User::ROLE_COMERCIAL, User::ROLE_ADMIN])],
            'condicion_credito' => ['nullable', Rule::in(Client::CREDIT_CONDITIONS)],
            'dias_credito' => ['nullable', 'required_if:condicion_credito,'.Client::CREDIT_TERM, 'integer', 'min:1', 'max:365'],
            'responsable_users' => ['nullable', 'array'],
            'responsable_users.*' => ['integer', $activeUserWithRole(User::INTERNAL_ROLES)],
            'tracking_users' => ['nullable', 'array'],
            'tracking_users.*' => ['integer', $activeUserWithRole(User::INTERNAL_ROLES)],
        ];

        return array_filter(
            $rules,
            fn (string $key) => in_array(explode('.', $key)[0], $editable, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function attributeLabels(): array
    {
        return [
            'client_id' => 'cliente',
            'customer_id' => 'Customer Service',
            'operador_id' => 'operador',
            'comercial_id' => 'comercial',
            'condicion_credito' => 'condicion de credito',
            'dias_credito' => 'dias de credito',
            'fecha_etd' => 'ETD',
            'fecha_eta' => 'ETA',
            'fecha_cut_off' => 'cut-off',
            'dias_libres_demurrage' => 'días libres de demurrage',
            'dias_libres_detention' => 'días libres de detention',
            'dias_libres_almacen' => 'días libres de almacén',
            'ubicacion_latitud' => 'latitud',
            'ubicacion_longitud' => 'longitud',
            'peso' => 'peso (kg)',
            'volumen' => 'volumen (CBM)',
            'rates' => 'tarifas de venta',
            'rates.*.rate_concept_id' => 'concepto de la tarifa',
            'rates.*.monto' => 'monto de la tarifa',
            'rates.*.moneda' => 'moneda de la tarifa',
            'tipo_cambio' => 'tipo de cambio',
        ];
    }

    /** Quita las filas de tarifa vacias que deja el formulario dinamico. */
    private function discardEmptyRateRows(Request $request): void
    {
        if (! $request->has('rates')) {
            return;
        }

        $rows = collect((array) $request->input('rates'))
            ->filter(fn ($row) => is_array($row) && (filled($row['rate_concept_id'] ?? null) || filled($row['monto'] ?? null)))
            ->values()
            ->all();

        $request->merge(['rates' => $rows]);
    }

    private function normalizedRates(iterable $rates): array
    {
        return collect($rates)
            ->map(fn ($rate) => [
                'rate_concept_id' => (int) data_get($rate, 'rate_concept_id'),
                'monto' => number_format((float) data_get($rate, 'monto'), 2, '.', ''),
                'moneda' => (string) data_get($rate, 'moneda'),
                'observacion' => filled(data_get($rate, 'observacion')) ? trim((string) data_get($rate, 'observacion')) : null,
            ])
            ->values()
            ->all();
    }

    private function ratesWillChange(Shipment $shipment, array $rates): bool
    {
        return $this->normalizedRates($shipment->rates) !== $this->normalizedRates($rates);
    }

    /** Reemplaza las tarifas de venta de la RO por las enviadas en el formulario. */
    private function syncRates(Shipment $shipment, array $rates): void
    {
        $shipment->rates()->delete();

        foreach ($this->normalizedRates($rates) as $rate) {
            $shipment->rates()->create($rate);
        }
    }

    private function shipmentAttributes(array $validated): array
    {
        return Arr::except($validated, self::NON_ATTRIBUTE_FIELDS);
    }

    /**
     * Condicion de credito del embarque:
     * - Por defecto se hereda de la ficha del cliente.
     * - Si el Admin la define, se fuerza solo para este embarque.
     * - Si el Admin la deja vacia, vuelve a heredarse.
     */
    private function applyCredit(Shipment $shipment, Client $client, array $validated): void
    {
        $adminDefinesCredit = array_key_exists('condicion_credito', $validated);

        if ($adminDefinesCredit && filled($validated['condicion_credito'])) {
            $shipment->overrideCredit(
                $client,
                $validated['condicion_credito'],
                isset($validated['dias_credito']) ? (int) $validated['dias_credito'] : null
            );

            return;
        }

        $mustInherit = ! $shipment->exists
            || $adminDefinesCredit
            || ($shipment->isDirty('client_id') && ! $shipment->credito_forzado);

        if ($mustInherit) {
            $shipment->inheritCreditFrom($client);
        }
    }

    private function buildIndexQuery(User $user, string $search, mixed $clientId, string $lifecycle, string $type = '', bool $onlyMine = false)
    {
        $preferences = UserShipmentPreference::query()
            ->select(['shipment_id', 'is_pinned'])
            ->where('user_id', $user->id);

        $query = Shipment::query()
            ->with('client')
            ->leftJoinSub($preferences, 'shipment_preferences', function ($join) {
                $join->on('shipments.id', '=', 'shipment_preferences.shipment_id');
            })
            ->select('shipments.*')
            ->selectRaw('COALESCE(shipment_preferences.is_pinned, 0) as is_pinned_for_user');

        if ($user->role === User::ROLE_CLIENTE) {
            $query->where('client_id', $user->client_id);
        } elseif (filled($clientId)) {
            $query->where('client_id', $clientId);
        }

        if ($search !== '') {
            $query->where(function ($shipmentQuery) use ($search) {
                $shipmentQuery
                    ->where('nro_orden_trabajo', 'like', "%{$search}%")
                    ->orWhere('bl', 'like', "%{$search}%")
                    ->orWhere('routing_order', 'like', "%{$search}%")
                    ->orWhere('nave', 'like', "%{$search}%")
                    ->orWhere('tracking_code', 'like', "%{$search}%");
            });
        }

        if ($lifecycle === 'active') {
            $query->where('estado_actual', '!=', 'Finalizado');
        } elseif ($lifecycle === 'finished') {
            $query->where('estado_actual', 'Finalizado');
        }

        if ($type !== '') {
            $query->where('shipments.tipo_carga', $type);
        }

        if ($onlyMine) {
            $query->linkedTo($user);
        }

        return $query;
    }

    private function syncAssignments(Shipment $shipment, Request $request): void
    {
        $responsableIds = collect($request->input('responsable_users', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $trackingIds = collect($request->input('tracking_users', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $responsableIds->contains($id))
            ->unique()
            ->values();

        $responsablePayload = $responsableIds
            ->mapWithKeys(fn (int $id) => [
                $id => [
                    'assignment_type' => ShipmentUserAssignment::TYPE_RESPONSABLE,
                    'alerts_enabled' => true,
                ],
            ])
            ->all();

        $trackingPayload = $trackingIds
            ->mapWithKeys(fn (int $id) => [
                $id => [
                    'assignment_type' => ShipmentUserAssignment::TYPE_SEGUIMIENTO,
                    'alerts_enabled' => true,
                ],
            ])
            ->all();

        $shipment->assignedUsers()->sync($responsablePayload + $trackingPayload);
    }

    private function shipmentAssignmentsWillChange(Shipment $shipment, Request $request): bool
    {
        $requestedResponsableIds = collect($request->input('responsable_users', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $currentAssignments = $shipment->userAssignments
            ->map(fn (ShipmentUserAssignment $assignment) => $assignment->assignment_type.':'.$assignment->user_id)
            ->sort()
            ->values()
            ->all();

        $requestedResponsables = $requestedResponsableIds
            ->map(fn (int $id) => ShipmentUserAssignment::TYPE_RESPONSABLE.':'.$id);

        $requestedTracking = collect($request->input('tracking_users', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $requestedResponsableIds->contains($id))
            ->unique()
            ->map(fn (int $id) => ShipmentUserAssignment::TYPE_SEGUIMIENTO.':'.$id);

        $requestedAssignments = $requestedResponsables
            ->concat($requestedTracking)
            ->sort()
            ->values()
            ->all();

        return $currentAssignments !== $requestedAssignments;
    }

    private function buildShipmentAuditDescription(array $fieldChanges, bool $hadAssignmentChanges): ?string
    {
        $labels = collect($fieldChanges)
            ->map(fn (string $field) => $this->shipmentFieldLabel($field))
            ->filter()
            ->values();

        if ($hadAssignmentChanges) {
            $labels->push('usuarios vinculados');
        }

        $labels = $labels->unique()->values();

        if ($labels->isEmpty()) {
            return null;
        }

        return 'Ficha actualizada: '.$labels->join(', ').'.';
    }

    private function shipmentFieldLabel(string $field): ?string
    {
        return [
            'client_id' => 'cliente',
            'comercial_id' => 'comercial',
            'customer_id' => 'customer service',
            'operador_id' => 'operador',
            'nro_orden_trabajo' => 'OT',
            'routing_order' => 'routing order',
            'bl' => 'BL',
            'tipo_carga' => 'tipo de carga',
            'incoterm' => 'incoterm',
            'condicion_credito' => 'condicion de credito',
            'dias_credito' => 'dias de credito',
            'origen' => 'pais de origen',
            'puerto_carga' => 'puerto de carga',
            'puerto_descarga' => 'puerto de descarga',
            'destino_final' => 'pais de llegada',
            'naviera' => 'naviera',
            'nave' => 'nave',
            'viaje' => 'viaje',
            'agente_origen' => 'agente de origen',
            'consignatario' => 'consignatario',
            'peso' => 'peso',
            'volumen' => 'volumen',
            'fecha_cut_off' => 'cut-off',
            'fecha_etd' => 'ETD',
            'fecha_eta' => 'ETA',
            'fecha_descarga' => 'fecha de descarga',
            'dias_libres_demurrage' => 'dias libres de demurrage',
            'dias_libres_detention' => 'dias libres de detention',
            'dias_libres_almacen' => 'dias libres de almacen',
            'ubicacion_actual' => 'ubicacion actual',
            'ubicacion_latitud' => 'ubicacion actual',
            'ubicacion_longitud' => 'ubicacion actual',
            'almacen_direccionamiento' => 'almacen de direccionamiento',
            'fecha_tarja' => 'tarja',
            'bultos_tarjados' => 'tarja',
            'peso_tarjado_kg' => 'tarja',
            'fecha_ingreso_almacen' => 'ingreso a almacen',
            'observacion_tarja' => 'tarja',
            'estado_actual' => 'estado actual',
            'estado_endoso' => 'estado de endoso',
            'fecha_endoso' => 'fecha de endoso',
            'responsable_endoso_id' => 'responsable de endoso',
            'observacion_endoso' => 'observacion de endoso',
            'observaciones' => 'observaciones',
            'rates' => 'tarifas de venta',
            'tipo_cambio' => 'tipo de cambio',
        ][$field] ?? null;
    }
}
