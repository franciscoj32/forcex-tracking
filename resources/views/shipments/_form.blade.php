@csrf

@php
    /*
     * Formulario de RO / embarque.
     * $editableFields viene de App\Support\ShipmentFieldAccess: los campos que
     * no estan en la lista se muestran como texto y el servidor los ignora.
     * Una seccion sin campos editables para el rol se muestra plegada.
     */
    $editable = fn (string $field): bool => in_array($field, $editableFields, true);
    $anyEditable = fn (array $fields): bool => array_intersect($fields, $editableFields) !== [];
    // En una RO nueva se ocultan las secciones que el rol no completa.
    $showSection = fn (array $fields): bool => $shipment->exists || $anyEditable($fields);

    $userOption = fn ($user) => [
        'value' => (string) $user->id,
        'label' => $user->name,
        'subtitle' => $user->email.' - @'.$user->username,
        'search' => strtolower($user->name.' '.$user->email.' '.$user->username.' '.$user->role),
    ];

    $clientOptions = collect($clients)->map(fn ($client) => [
        'value' => (string) $client->id,
        'label' => $client->razon_social.' · '.$client->creditLabel(),
    ]);
    $typeOptions = collect($types)->map(fn ($type) => ['value' => $type, 'label' => $type]);
    $incotermOptions = collect($incoterms)->map(fn ($incoterm) => ['value' => $incoterm, 'label' => $incoterm]);
    $trackingStateOptions = collect($trackingStates)->map(fn ($state) => ['value' => $state, 'label' => $state]);
    $endorsementStatusOptions = collect($endorsementStatuses)
        ->map(fn ($status) => ['value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))]);
    $countryOptions = collect($countries)->map(fn ($country) => ['value' => $country, 'label' => $country]);
    $creditOptions = collect($creditConditions)
        ->map(fn ($condition) => ['value' => $condition, 'label' => $condition === 'credito' ? 'Crédito' : 'Pago contado']);

    $customerOptions = collect($customerUsers)->map(fn ($user) => ['value' => (string) $user->id, 'label' => $user->name]);
    $operatorOptions = collect($operatorUsers)->map(fn ($user) => ['value' => (string) $user->id, 'label' => $user->name]);
    $commercialOptions = collect($commercialUsers)->map(fn ($user) => ['value' => (string) $user->id, 'label' => $user->name.' ('.$user->role_label.')']);
    $endorsementUserOptions = collect($endorsementUsers)->map(fn ($user) => [
        'value' => (string) $user->id,
        'label' => $user->name.' - @'.$user->username,
    ]);
    $assignmentUserOptions = collect($assignmentUsers)->map($userOption);

    $assignments = $shipment->relationLoaded('userAssignments') ? $shipment->userAssignments : collect();
    $selectedResponsibleUsers = collect(old(
        'responsable_users',
        $assignments->where('assignment_type', \App\Models\ShipmentUserAssignment::TYPE_RESPONSABLE)->pluck('user_id')->all()
    ))->map(fn ($id) => (string) $id)->all();
    $selectedTrackingUsers = collect(old(
        'tracking_users',
        $assignments->where('assignment_type', \App\Models\ShipmentUserAssignment::TYPE_SEGUIMIENTO)->pluck('user_id')->all()
    ))->map(fn ($id) => (string) $id)->all();

    $dateValue = fn (string $field) => old($field, optional($shipment->{$field})->format('Y-m-d'));
    $dateLabel = fn (string $field) => optional($shipment->{$field})->format('d/m/Y');
    $dateTimeValue = fn (string $field) => old($field, optional($shipment->{$field})->format('Y-m-d H:i'));
    $dateTimeLabel = fn (string $field) => optional($shipment->{$field})->format('d/m/Y H:i');

    $comercialFields = \App\Support\ShipmentFieldAccess::COMERCIAL;
    // Los puertos se muestran en la seccion comercial; aqui solo lo exclusivo de Operaciones.
    $operativeFields = array_values(array_diff(\App\Support\ShipmentFieldAccess::OPERATIVO, $comercialFields));

    // Campos de cada seccion (para decidir si se muestra abierta o plegada).
    $sectionFields = [
        'comercial' => ['client_id', 'nro_orden_trabajo', 'routing_order', 'tipo_carga', 'incoterm', 'consignatario', 'origen', 'puerto_carga', 'puerto_descarga', 'destino_final'],
        'responsables' => ['comercial_id', 'customer_id', 'operador_id'],
        'credito' => ['condicion_credito', 'tipo_cambio'],
        'operativo' => ['bl', 'naviera', 'nave', 'viaje', 'agente_origen', 'fecha_cut_off', 'estado_actual', 'peso', 'volumen', 'estado_endoso', 'fecha_etd', 'fecha_eta', 'fecha_descarga', 'dias_libres_demurrage', 'dias_libres_detention', 'fecha_endoso', 'responsable_endoso_id', 'observacion_endoso'],
        'ubicacion' => ['ubicacion_actual', 'ubicacion_latitud', 'ubicacion_longitud'],
        'tarja' => ['almacen_direccionamiento', 'fecha_tarja', 'fecha_ingreso_almacen', 'dias_libres_almacen', 'bultos_tarjados', 'peso_tarjado_kg', 'observacion_tarja'],
        'seguimiento' => ['responsable_users', 'tracking_users', 'observaciones'],
    ];
    $readonly = fn (string $section): bool => ! $anyEditable($sectionFields[$section]);
    $grid = 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3';
@endphp

<div id="country-options" class="hidden">{{ $countryOptions->pluck('label')->join(' | ') }}</div>

<div class="space-y-4">
    {{-- 1. Datos comerciales de la RO --}}
    <x-form-section title="Datos comerciales" id="comercial" :readonly="$readonly('comercial')">
        <div class="{{ $grid }}">
            <div>
                @if ($editable('client_id'))
                    <x-form-combobox
                        id="client_id"
                        name="client_id"
                        label="Cliente (solo aprobados)"
                        :options="$clientOptions"
                        :value="old('client_id', $shipment->client_id ?? '')"
                        placeholder="Seleccionar cliente"
                        search-placeholder="Buscar cliente..."
                        empty-text="No hay clientes aprobados que coincidan."
                        searchable
                        required
                    />
                @else
                    <x-form-locked label="Cliente" :value="$shipment->client?->razon_social" />
                @endif
            </div>
            <div>
                @if ($editable('nro_orden_trabajo'))
                    <label class="fx-label" for="nro_orden_trabajo">Nro. orden de trabajo</label>
                    <input class="fx-input" id="nro_orden_trabajo" name="nro_orden_trabajo" value="{{ old('nro_orden_trabajo', $shipment->nro_orden_trabajo) }}" required>
                @else
                    <x-form-locked label="Nro. orden de trabajo" :value="$shipment->nro_orden_trabajo" />
                @endif
            </div>
            <div>
                @if ($editable('routing_order'))
                    <label class="fx-label" for="routing_order">Routing order (RO)</label>
                    <input class="fx-input" id="routing_order" name="routing_order" value="{{ old('routing_order', $shipment->routing_order) }}" required>
                @else
                    <x-form-locked label="Routing order (RO)" :value="$shipment->routing_order" />
                @endif
            </div>
            <div>
                @if ($editable('tipo_carga'))
                    <x-form-combobox id="tipo_carga" name="tipo_carga" label="Tipo de carga" :options="$typeOptions"
                        :value="old('tipo_carga', $shipment->tipo_carga ?? '')" placeholder="Seleccionar tipo" required />
                @else
                    <x-form-locked label="Tipo de carga" :value="$shipment->tipo_carga" />
                @endif
            </div>
            <div>
                @if ($editable('incoterm'))
                    <x-form-combobox id="incoterm" name="incoterm" label="Incoterm" :options="$incotermOptions"
                        :value="old('incoterm', $shipment->incoterm ?? '')" placeholder="Seleccionar incoterm" required />
                @else
                    <x-form-locked label="Incoterm" :value="$shipment->incoterm" />
                @endif
            </div>
            <div>
                @if ($editable('consignatario'))
                    <label class="fx-label" for="consignatario">Consignatario</label>
                    <input class="fx-input" id="consignatario" name="consignatario" value="{{ old('consignatario', $shipment->consignatario) }}">
                @else
                    <x-form-locked label="Consignatario" :value="$shipment->consignatario" />
                @endif
            </div>
            <div>
                @if ($editable('origen'))
                    <x-form-autocomplete id="origen" name="origen" label="Pais de origen" :options="$countryOptions"
                        :value="old('origen', $shipment->origen ?? '')" placeholder="Empieza a escribir el país"
                        search-placeholder="Buscar país..." empty-text="No hay países que coincidan." required />
                @else
                    <x-form-locked label="País de origen" :value="$shipment->origen" />
                @endif
            </div>
            <div>
                @if ($editable('puerto_carga'))
                    <label class="fx-label" for="puerto_carga">Puerto / aeropuerto de carga</label>
                    <input class="fx-input" id="puerto_carga" name="puerto_carga" value="{{ old('puerto_carga', $shipment->puerto_carga) }}" required>
                @else
                    <x-form-locked label="Puerto / aeropuerto de carga" :value="$shipment->puerto_carga" />
                @endif
            </div>
            <div>
                @if ($editable('puerto_descarga'))
                    <label class="fx-label" for="puerto_descarga">Puerto / aeropuerto de descarga</label>
                    <input class="fx-input" id="puerto_descarga" name="puerto_descarga" value="{{ old('puerto_descarga', $shipment->puerto_descarga) }}" required>
                @else
                    <x-form-locked label="Puerto / aeropuerto de descarga" :value="$shipment->puerto_descarga" />
                @endif
            </div>
            <div>
                @if ($editable('destino_final'))
                    <x-form-autocomplete id="destino_final" name="destino_final" label="Pais de llegada" :options="$countryOptions"
                        :value="old('destino_final', $shipment->destino_final ?? '')" placeholder="Empieza a escribir el país"
                        search-placeholder="Buscar país..." empty-text="No hay países que coincidan." required />
                @else
                    <x-form-locked label="País de llegada" :value="$shipment->destino_final" />
                @endif
            </div>
        </div>
    </x-form-section>

    {{-- 1b. Tarifas de venta pactadas --}}
    @php
        $rateRows = collect(old('rates', $shipment->relationLoaded('rates')
            ? $shipment->rates->map(fn ($rate) => [
                'rate_concept_id' => (string) $rate->rate_concept_id,
                'monto' => (string) $rate->monto,
                'moneda' => $rate->moneda,
                'observacion' => (string) $rate->observacion,
            ])->all()
            : []))
            ->map(fn ($row) => [
                'rate_concept_id' => (string) ($row['rate_concept_id'] ?? ''),
                'monto' => (string) ($row['monto'] ?? ''),
                'moneda' => (string) ($row['moneda'] ?? 'USD'),
                'observacion' => (string) ($row['observacion'] ?? ''),
            ])
            ->values()
            ->all();
        $canSeeRates = $shipment->exists ? auth()->user()->can('viewRates', $shipment) : true;
    @endphp

    @if ($editable('rates'))
        <section
            id="tarifas"
            class="fx-card scroll-mt-20"
            x-data="{
                rows: @js($rateRows === [] ? [['rate_concept_id' => '', 'monto' => '', 'moneda' => 'USD', 'observacion' => '']] : $rateRows),
                add() { this.rows.push({ rate_concept_id: '', monto: '', moneda: 'USD', observacion: '' }); },
                remove(index) { this.rows.splice(index, 1); if (this.rows.length === 0) { this.add(); } },
                total(currency) {
                    return this.rows
                        .filter((row) => row.moneda === currency)
                        .reduce((sum, row) => sum + (parseFloat(row.monto) || 0), 0)
                        .toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            }"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <h2 class="fx-section-title">Tarifas de venta</h2>
                <p class="text-sm tabular-nums text-slate-700">
                    Total: US$ <span x-text="total('USD')"></span> · S/ <span x-text="total('PEN')"></span>
                </p>
            </div>

            <div class="space-y-3 p-4">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="grid gap-3 border-b border-slate-100 pb-3 last:border-0 last:pb-0 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_100px_minmax(0,2fr)_auto] md:items-end">
                        <div>
                            <label class="fx-label" :for="`rate_concept_${index}`">Concepto</label>
                            <select class="fx-select" :id="`rate_concept_${index}`" :name="`rates[${index}][rate_concept_id]`" x-model="row.rate_concept_id" required>
                                <option value="">Seleccionar concepto</option>
                                @foreach ($rateConcepts as $concept)
                                    <option value="{{ $concept->id }}">{{ $concept->nombre }}{{ $concept->activo ? '' : ' (inactivo)' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fx-label" :for="`rate_amount_${index}`">Monto</label>
                            <input class="fx-input" :id="`rate_amount_${index}`" :name="`rates[${index}][monto]`" x-model="row.monto" type="number" min="0.01" step="0.01" required>
                        </div>
                        <div>
                            <label class="fx-label" :for="`rate_currency_${index}`">Moneda</label>
                            <select class="fx-select" :id="`rate_currency_${index}`" :name="`rates[${index}][moneda]`" x-model="row.moneda">
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency }}">{{ $currency }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fx-label" :for="`rate_note_${index}`">Observación</label>
                            <input class="fx-input" :id="`rate_note_${index}`" :name="`rates[${index}][observacion]`" x-model="row.observacion" maxlength="255" placeholder="Opcional">
                        </div>
                        <button type="button" class="fx-btn-secondary text-rose-700" x-on:click="remove(index)" aria-label="Quitar tarifa">Quitar</button>
                    </div>
                </template>
                <button type="button" class="fx-btn-link-primary" x-on:click="add()">+ Agregar concepto</button>
            </div>
        </section>
    @elseif ($shipment->exists && $canSeeRates)
        <x-form-section title="Tarifas de venta" id="tarifas" readonly>
            <div class="grid gap-x-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($shipment->rates as $rate)
                    <x-form-locked :label="$rate->concept?->nombre ?? 'Concepto'" :value="\App\Support\Money::format((float) $rate->monto, $rate->moneda)" />
                @empty
                    <p class="text-slate-500">Sin tarifas.</p>
                @endforelse
            </div>
        </x-form-section>
    @endif

    {{-- 2. Responsables --}}
    <x-form-section title="Responsables" id="responsables" :readonly="$readonly('responsables')">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                @if ($editable('comercial_id'))
                    <x-form-combobox id="comercial_id" name="comercial_id" label="Comercial" :options="$commercialOptions"
                        :value="old('comercial_id', $shipment->comercial_id ?? '')" placeholder="Seleccionar comercial"
                        all-label="Sin comercial (venta directa)" searchable search-placeholder="Buscar comercial..." />
                @else
                    <x-form-locked label="Comercial"
                        :value="$shipment->comercial?->name ?? ($shipment->exists ? null : auth()->user()->name)" />
                @endif
            </div>
            <div>
                @if ($editable('customer_id'))
                    <x-form-combobox id="customer_id" name="customer_id" label="Customer Service" :options="$customerOptions"
                        :value="old('customer_id', $shipment->customer_id ?? '')" placeholder="Seleccionar Customer"
                        searchable search-placeholder="Buscar Customer..." empty-text="No hay usuarios Customer activos." required />
                @else
                    <x-form-locked label="Customer Service" :value="$shipment->customerService?->name" />
                @endif
            </div>
            <div>
                @if ($editable('operador_id'))
                    <x-form-combobox id="operador_id" name="operador_id" label="Operador" :options="$operatorOptions"
                        :value="old('operador_id', $shipment->operador_id ?? '')" placeholder="Seleccionar operador"
                        all-label="Sin asignar" searchable search-placeholder="Buscar operador..." />
                @else
                    <x-form-locked label="Operador" :value="$shipment->operador?->name ?? 'Lo asigna el Customer'" />
                @endif
            </div>
        </div>
    </x-form-section>

    {{-- 3. Condicion de credito y tipo de cambio --}}
    <x-form-section title="Crédito y tipo de cambio" id="credito" :readonly="$readonly('credito')">
        <div class="grid gap-4 sm:grid-cols-3" x-data="{ condition: @js(old('condicion_credito', $shipment->exists ? $shipment->condicion_credito : '')) }">
            @if ($editable('condicion_credito'))
                <div>
                    <label class="fx-label" for="condicion_credito">Condición del embarque</label>
                    <select class="fx-select" id="condicion_credito" name="condicion_credito" x-model="condition">
                        <option value="">Heredar del cliente{{ $shipment->exists && $shipment->client ? ' ('.$shipment->client->creditLabel().')' : '' }}</option>
                        @foreach ($creditOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="condition === 'credito'" x-cloak>
                    <label class="fx-label" for="dias_credito">Días de crédito</label>
                    <input class="fx-input" id="dias_credito" name="dias_credito" type="number" min="1" max="365"
                        value="{{ old('dias_credito', $shipment->dias_credito) }}">
                </div>
            @else
                <x-form-locked label="Condición del embarque"
                    :value="$shipment->exists ? $shipment->creditLabel().($shipment->credito_forzado ? ' (forzado por Admin)' : ' (del cliente)') : 'Se hereda del cliente'" />
            @endif

            @if ($editable('tipo_cambio'))
                <div>
                    <label class="fx-label" for="tipo_cambio">Tipo de cambio (S/ por US$, vacío = referencia del día)</label>
                    <input class="fx-input" id="tipo_cambio" name="tipo_cambio" type="number" min="1" max="10" step="0.0001"
                        value="{{ old('tipo_cambio', $shipment->tipo_cambio) }}"
                        placeholder="{{ $referenceExchangeRate ? number_format($referenceExchangeRate, 4) : 'Sin referencia' }}">
                </div>
            @else
                <x-form-locked label="Tipo de cambio de la RO"
                    :value="$shipment->exists
                        ? ($shipment->tipo_cambio ? number_format((float) $shipment->tipo_cambio, 4) : 'Sin tipo de cambio')
                        : ($referenceExchangeRate ? number_format($referenceExchangeRate, 4).' (referencia del día)' : 'Se toma la referencia del día')" />
            @endif
        </div>
    </x-form-section>

    {{-- 4. Datos operativos --}}
    @if ($showSection(array_merge($operativeFields, ['estado_actual'])))
        <x-form-section title="Datos operativos" id="operativo" :readonly="$readonly('operativo')">
            <div class="{{ $grid }}">
                <div>
                    @if ($editable('bl'))
                        <label class="fx-label" for="bl">BL / AWB</label>
                        <input class="fx-input" id="bl" name="bl" value="{{ old('bl', $shipment->bl) }}">
                    @else
                        <x-form-locked label="BL / AWB" :value="$shipment->bl" />
                    @endif
                </div>
                <div>
                    @if ($editable('naviera'))
                        <label class="fx-label" for="naviera">Línea naviera / aerolínea</label>
                        <input class="fx-input" id="naviera" name="naviera" value="{{ old('naviera', $shipment->naviera) }}">
                    @else
                        <x-form-locked label="Línea naviera / aerolínea" :value="$shipment->naviera" />
                    @endif
                </div>
                <div>
                    @if ($editable('nave'))
                        <label class="fx-label" for="nave">{{ $shipment->isAirCargo() ? 'Vuelo' : 'Nave (buque)' }}</label>
                        <input class="fx-input uppercase" id="nave" name="nave" maxlength="120" placeholder="{{ $shipment->isAirCargo() ? 'Ej. LA2401' : 'Ej. MSC ANNA' }}"
                            value="{{ old('nave', $shipment->nave) }}">
                    @else
                        <x-form-locked :label="$shipment->isAirCargo() ? 'Vuelo' : 'Nave (buque)'" :value="$shipment->nave" />
                    @endif
                </div>
                <div>
                    @if ($editable('viaje'))
                        <label class="fx-label" for="viaje">Viaje</label>
                        <input class="fx-input uppercase" id="viaje" name="viaje" maxlength="60" placeholder="Ej. 442W"
                            value="{{ old('viaje', $shipment->viaje) }}">
                    @else
                        <x-form-locked label="Viaje" :value="$shipment->viaje" />
                    @endif
                </div>
                <div>
                    @if ($editable('agente_origen'))
                        <label class="fx-label" for="agente_origen">Agente de origen</label>
                        <input class="fx-input" id="agente_origen" name="agente_origen" value="{{ old('agente_origen', $shipment->agente_origen) }}">
                    @else
                        <x-form-locked label="Agente de origen" :value="$shipment->agente_origen" />
                    @endif
                </div>
                <div>
                    @if ($shipment->exists && $editable('estado_actual'))
                        <x-form-combobox id="estado_actual" name="estado_actual" label="Estado actual" :options="$trackingStateOptions"
                            :value="old('estado_actual', $shipment->estado_actual)" placeholder="Seleccionar estado"
                            search-placeholder="Buscar estado..." empty-text="No hay estados que coincidan." searchable required />
                    @else
                        <x-form-locked label="Estado actual" :value="$shipment->estado_actual" />
                    @endif
                </div>
                <div>
                    @if ($editable('peso'))
                        <label class="fx-label" for="peso">Peso (kg)</label>
                        <input class="fx-input" id="peso" name="peso" type="number" step="0.01" min="0" value="{{ old('peso', $shipment->peso) }}">
                    @else
                        <x-form-locked label="Peso" :value="$shipment->peso ? $shipment->weightInTons() : null" />
                    @endif
                </div>
                <div>
                    @if ($editable('volumen'))
                        <label class="fx-label" for="volumen">Volumen (CBM)</label>
                        <input class="fx-input" id="volumen" name="volumen" type="number" step="0.01" min="0" value="{{ old('volumen', $shipment->volumen) }}">
                    @else
                        <x-form-locked label="Volumen (CBM)" :value="$shipment->volumen" />
                    @endif
                </div>
            </div>

            <h3 class="mt-6 text-sm font-semibold text-slate-900">Fechas</h3>
            @if ($editable('fecha_eta') && $shipment->exists)
                <p class="mt-1 text-sm text-amber-800">Si cambias ETD o ETA, el cliente verá la nueva fecha cuando el Customer la valide.</p>
            @endif
            <div class="mt-3 {{ $grid }}">
                <div>
                    @if ($editable('fecha_cut_off'))
                        <x-form-date-picker id="fecha_cut_off" name="fecha_cut_off" label="Cut-off"
                            :value="$dateTimeValue('fecha_cut_off')" placeholder="Fecha y hora de cierre" enable-time />
                    @else
                        <x-form-locked label="Cut-off" :value="$dateTimeLabel('fecha_cut_off')" />
                    @endif
                </div>
                @foreach (['fecha_etd' => 'ETD', 'fecha_eta' => 'ETA', 'fecha_descarga' => 'Fecha de descarga'] as $dateField => $dateFieldLabel)
                    <div>
                        @if ($editable($dateField))
                            <x-form-date-picker :id="$dateField" :name="$dateField" :label="$dateFieldLabel"
                                :value="$dateValue($dateField)" :placeholder="'Seleccionar '.$dateFieldLabel" />
                        @else
                            <x-form-locked :label="$dateFieldLabel" :value="$dateLabel($dateField)" />
                        @endif
                    </div>
                @endforeach
            </div>

            @unless ($shipment->isAirCargo())
                <h3 class="mt-6 text-sm font-semibold text-slate-900">Días libres del booking (FCL)</h3>
                <p class="mt-1 text-sm text-slate-500">Días calendario. El demurrage cuenta desde la descarga (día 1); la detention, desde el retiro de cada contenedor.</p>
                <div class="mt-3 {{ $grid }}">
                    @foreach (['dias_libres_demurrage' => 'Demurrage (en puerto)', 'dias_libres_detention' => 'Detention (devolver vacío)'] as $freeDaysField => $freeDaysLabel)
                        <div>
                            @if ($editable($freeDaysField))
                                <label class="fx-label" for="{{ $freeDaysField }}">{{ $freeDaysLabel }}</label>
                                <input class="fx-input" id="{{ $freeDaysField }}" name="{{ $freeDaysField }}" type="number" min="0" max="365" step="1"
                                    inputmode="numeric" placeholder="Ej. 7" value="{{ old($freeDaysField, $shipment->{$freeDaysField}) }}">
                            @else
                                <x-form-locked :label="$freeDaysLabel" :value="$shipment->{$freeDaysField} !== null ? $shipment->{$freeDaysField}.' días' : null" />
                            @endif
                        </div>
                    @endforeach
                </div>
            @endunless

            <h3 class="mt-6 text-sm font-semibold text-slate-900">Endoso</h3>
            <div class="mt-3 {{ $grid }}">
                <div>
                    @if ($editable('estado_endoso'))
                        <x-form-combobox id="estado_endoso" name="estado_endoso" label="Estado de endoso" :options="$endorsementStatusOptions"
                            :value="old('estado_endoso', $shipment->estado_endoso ?? 'pendiente')" placeholder="Seleccionar endoso" required />
                    @else
                        <x-form-locked label="Estado de endoso" :value="ucfirst(str_replace('_', ' ', (string) $shipment->estado_endoso))" />
                    @endif
                </div>
                <div>
                    @if ($editable('fecha_endoso'))
                        <x-form-date-picker id="fecha_endoso" name="fecha_endoso" label="Fecha de endoso"
                            :value="$dateValue('fecha_endoso')" placeholder="Seleccionar fecha" />
                    @else
                        <x-form-locked label="Fecha de endoso" :value="$dateLabel('fecha_endoso')" />
                    @endif
                </div>
                <div>
                    @if ($editable('responsable_endoso_id'))
                        <x-form-combobox id="responsable_endoso_id" name="responsable_endoso_id" label="Responsable de endoso"
                            :options="$endorsementUserOptions" :value="old('responsable_endoso_id', $shipment->responsable_endoso_id ?? '')"
                            placeholder="Seleccionar responsable" all-label="Sin asignar" search-placeholder="Buscar responsable..."
                            empty-text="No hay responsables que coincidan." searchable />
                    @else
                        <x-form-locked label="Responsable de endoso" :value="$shipment->endorsementResponsible?->name" />
                    @endif
                </div>
                <div class="sm:col-span-2 xl:col-span-3">
                    @if ($editable('observacion_endoso'))
                        <label class="fx-label" for="observacion_endoso">Observación de endoso</label>
                        <textarea class="fx-textarea" id="observacion_endoso" name="observacion_endoso" rows="2">{{ old('observacion_endoso', $shipment->observacion_endoso) }}</textarea>
                    @else
                        <x-form-locked label="Observación de endoso" :value="$shipment->observacion_endoso" />
                    @endif
                </div>
            </div>
        </x-form-section>

        {{-- 4b. Donde esta ahora --}}
        <x-form-section title="Dónde está ahora (lo ve el cliente)" id="ubicacion" :readonly="$readonly('ubicacion')">
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="sm:col-span-2">
                    @if ($editable('ubicacion_actual'))
                        <label class="fx-label" for="ubicacion_actual">Ubicación actual</label>
                        <input class="fx-input" id="ubicacion_actual" name="ubicacion_actual" maxlength="255"
                            placeholder="Ej. En navegación, Océano Pacífico frente a Panamá" value="{{ old('ubicacion_actual', $shipment->ubicacion_actual) }}">
                    @else
                        <x-form-locked label="Ubicación actual" :value="$shipment->ubicacion_actual" />
                    @endif
                </div>
                @foreach (['ubicacion_latitud' => 'Latitud (opcional)', 'ubicacion_longitud' => 'Longitud (opcional)'] as $coordField => $coordLabel)
                    <div>
                        @if ($editable($coordField))
                            <label class="fx-label" for="{{ $coordField }}">{{ $coordLabel }}</label>
                            <input class="fx-input" id="{{ $coordField }}" name="{{ $coordField }}" type="number" step="0.0000001"
                                value="{{ old($coordField, $shipment->{$coordField}) }}">
                        @else
                            <x-form-locked :label="$coordLabel" :value="$shipment->{$coordField}" />
                        @endif
                    </div>
                @endforeach
            </div>
            @if ($shipment->ubicacion_actualizada_en)
                <p class="mt-2 text-xs text-slate-500">Actualizada el {{ $shipment->ubicacion_actualizada_en->format('d/m/Y H:i') }}</p>
            @endif
        </x-form-section>

        {{-- 4c. Tarja y almacen --}}
        <x-form-section title="Tarja y almacén" id="tarja" :readonly="$readonly('tarja')">
            <div class="{{ $grid }}">
                <div>
                    @if ($editable('almacen_direccionamiento'))
                        <label class="fx-label" for="almacen_direccionamiento">Almacén de direccionamiento</label>
                        <input class="fx-input" id="almacen_direccionamiento" name="almacen_direccionamiento"
                            placeholder="Ej. Neptunia, Ransa, DP World" value="{{ old('almacen_direccionamiento', $shipment->almacen_direccionamiento) }}">
                    @else
                        <x-form-locked label="Almacén de direccionamiento" :value="$shipment->almacen_direccionamiento" />
                    @endif
                </div>
                @foreach (['fecha_tarja' => 'Fecha de tarja', 'fecha_ingreso_almacen' => 'Ingreso a almacén'] as $dateTimeField => $dateTimeFieldLabel)
                    <div>
                        @if ($editable($dateTimeField))
                            <x-form-date-picker :id="$dateTimeField" :name="$dateTimeField" :label="$dateTimeFieldLabel"
                                :value="$dateTimeValue($dateTimeField)" placeholder="Fecha y hora" enable-time />
                        @else
                            <x-form-locked :label="$dateTimeFieldLabel" :value="$dateTimeLabel($dateTimeField)" />
                        @endif
                    </div>
                @endforeach
                <div>
                    @if ($editable('dias_libres_almacen'))
                        <label class="fx-label" for="dias_libres_almacen">Días libres de almacenaje</label>
                        <input class="fx-input" id="dias_libres_almacen" name="dias_libres_almacen" type="number" min="0" max="365" step="1"
                            inputmode="numeric" placeholder="Ej. 7" value="{{ old('dias_libres_almacen', $shipment->dias_libres_almacen) }}">
                        <p class="mt-1 text-xs text-slate-500">Desde el ingreso a almacén (día 1).</p>
                    @else
                        <x-form-locked label="Días libres de almacenaje" :value="$shipment->dias_libres_almacen !== null ? $shipment->dias_libres_almacen.' días' : null" />
                    @endif
                </div>
                <div>
                    @if ($editable('bultos_tarjados'))
                        <label class="fx-label" for="bultos_tarjados">Bultos tarjados</label>
                        <input class="fx-input" id="bultos_tarjados" name="bultos_tarjados" type="number" min="0" step="1"
                            value="{{ old('bultos_tarjados', $shipment->bultos_tarjados) }}">
                    @else
                        <x-form-locked label="Bultos tarjados" :value="$shipment->bultos_tarjados" />
                    @endif
                </div>
                <div>
                    @if ($editable('peso_tarjado_kg'))
                        <label class="fx-label" for="peso_tarjado_kg">Peso tarjado (kg)</label>
                        <input class="fx-input" id="peso_tarjado_kg" name="peso_tarjado_kg" type="number" min="0" step="0.01"
                            value="{{ old('peso_tarjado_kg', $shipment->peso_tarjado_kg) }}">
                    @else
                        <x-form-locked label="Peso tarjado" :value="$shipment->peso_tarjado_kg !== null ? \App\Models\Shipment::formatTons($shipment->peso_tarjado_kg) : null" />
                    @endif
                </div>
                <div class="sm:col-span-2 xl:col-span-3">
                    @if ($editable('observacion_tarja'))
                        <label class="fx-label" for="observacion_tarja">Observación de tarja</label>
                        <textarea class="fx-textarea" id="observacion_tarja" name="observacion_tarja" rows="2"
                            placeholder="Faltantes, sobrantes, daños o diferencias de peso">{{ old('observacion_tarja', $shipment->observacion_tarja) }}</textarea>
                    @else
                        <x-form-locked label="Observación de tarja" :value="$shipment->observacion_tarja" />
                    @endif
                </div>
            </div>
        </x-form-section>
    @endif

    {{-- 5. Alertas y notas internas --}}
    <x-form-section title="Alertas y observaciones" id="seguimiento" :readonly="$readonly('seguimiento')">
        <div class="grid gap-4 md:grid-cols-2">
            @if ($editable('responsable_users'))
                <x-form-multiselect-tags id="responsable_users" name="responsable_users" label="Responsables (reciben alertas)"
                    :options="$assignmentUserOptions" :values="$selectedResponsibleUsers" placeholder="Buscar por correo o nombre"
                    search-placeholder="Correo, nombre o usuario..." empty-text="No hay usuarios que coincidan." />
                <x-form-multiselect-tags id="tracking_users" name="tracking_users" label="En seguimiento (reciben alertas)"
                    :options="$assignmentUserOptions" :values="$selectedTrackingUsers" placeholder="Buscar por correo o nombre"
                    search-placeholder="Correo, nombre o usuario..." empty-text="No hay usuarios que coincidan." />
            @endif

            <div class="md:col-span-2">
                @if ($editable('observaciones'))
                    <label class="fx-label" for="observaciones">Observaciones internas</label>
                    <textarea class="fx-textarea" id="observaciones" name="observaciones" rows="3">{{ old('observaciones', $shipment->observaciones) }}</textarea>
                @else
                    <x-form-locked label="Observaciones internas" :value="$shipment->observaciones" />
                @endif
            </div>
        </div>
    </x-form-section>
</div>

{{-- Barra de guardado siempre visible --}}
<div class="sticky bottom-0 z-10 -mx-4 mt-4 flex items-center gap-2 border-t border-slate-200 bg-white px-4 py-3 sm:-mx-6 sm:px-6">
    <button class="fx-btn-primary" type="submit">Guardar</button>
    <a href="{{ $shipment->exists ? route('embarques.show', $shipment) : route('embarques.index') }}" class="fx-btn-secondary">Cancelar</a>
</div>
