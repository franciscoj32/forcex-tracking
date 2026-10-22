@php
    $user = auth()->user();
    $currentStateIndex = $shipment->currentTrackingStateIndex();
    $stateCount = count($trackingStates);
    // Solo los hitos definen el recorrido; auditorias y notas no son estados.
    $timelineByState = $timelineEvents
        ->where('tipo', \App\Models\ShipmentEvent::TYPE_STATE)
        ->groupBy('estado')
        ->map(fn ($events) => $events->last());
    $canEditShipment = $user->can('update', $shipment);
    $canTrack = $user->can('registerTracking', $shipment);
    $canCommunicate = $user->can('communicateWithClient', $shipment);
    $canManageDocuments = $user->can('manageDocuments', $shipment);
    $canViewTrackingCode = $shipment->canRevealTrackingCodeTo($user);

    $initialTab = $activeTab ?? request('tab', 'datos');
    if ($errors->any()) {
        if (in_array(old('_form'), ['provision', 'invoice'], true) && $canSeeFinance) {
            $initialTab = 'finanzas';
        } elseif ($errors->hasAny(['categoria', 'tipo_documento', 'archivo'])) {
            $initialTab = 'documentos';
        } elseif ($errors->hasAny(['estado', 'fecha', 'descripcion', 'nota'])) {
            $initialTab = 'tracking';
        }
    }
    $documentFormHasErrors = $errors->hasAny(['categoria', 'tipo_documento', 'archivo']);

    $attention = \App\Support\ShipmentAttention::for($shipment, $user);
    // Plazos de la RO (alertas criticas). Los lejanos ("none") solo van como cuenta regresiva en Resumen.
    $now = \Carbon\CarbonImmutable::now();
    $shipmentAlerts = \App\Services\Alerts\AlertBoard::make()->forShipment($shipment, $now);
    $visibleAlerts = $shipmentAlerts->where('level', '!=', \App\Services\Alerts\AlertLevel::NONE)->values();
    $missingDocuments = \App\Support\ShipmentAttention::missingRequiredDocuments($shipment);
    $validationQueue = \App\Support\ShipmentAttention::documentValidationQueue($shipment);
    $isFinished = $shipment->estado_actual === 'Finalizado';
    $beforeArrival = in_array($shipment->estado_actual, \App\Models\Shipment::statesBeforeArrival(), true);
    $isDelayed = $beforeArrival && $shipment->fecha_eta && $shipment->fecha_eta->lt(now()->startOfDay());
    $etaDiffers = optional($shipment->fecha_eta)->toDateString() !== optional($shipment->fecha_eta_cliente)->toDateString();
    $cargoLabel = $shipment->tipo_carga === 'AEREO' ? 'Aéreo' : $shipment->tipo_carga;
    $route = ($shipment->puerto_carga ?: $shipment->origen).' → '.($shipment->puerto_descarga ?: $shipment->destino_final);

    $tabs = [
        'datos' => ['label' => 'Resumen', 'icon' => 'summary', 'badge' => null],
        'tracking' => ['label' => 'Seguimiento', 'icon' => 'tracking', 'badge' => null],
        'documentos' => ['label' => 'Documentos', 'icon' => 'documents', 'badge' => collect([
            count($missingDocuments) > 0 ? count($missingDocuments).' por subir' : null,
            $validationQueue['pending']->isNotEmpty() ? $validationQueue['pending']->count().' por validar' : null,
            $validationQueue['observed']->isNotEmpty() ? $validationQueue['observed']->count().' observado(s)' : null,
        ])->filter()->join(' · ') ?: null],
    ];
    if ($canSeeFinance) {
        $tabs['finanzas'] = ['label' => 'Finanzas', 'icon' => 'finance', 'badge' => null];
    }

    // Datos de la pestana Documentos
    $documentCategoryOptions = collect();
    $documentTypeOptions = collect();
    if ($initialTab === 'documentos') {
        $documentCategoryOptions = collect($documentCategories)->map(fn ($category) => ['value' => $category, 'label' => ucfirst($category)]);
        $documentTypeOptions = collect($documentTypeSuggestions)->flatten()->unique()->values()->map(fn ($item) => ['value' => $item, 'label' => $item]);
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block">
            <a href="{{ route('embarques.index') }}" class="hover:underline">Embarques</a> / {{ $shipment->nro_orden_trabajo }}
        </p>
        <h1>{{ $shipment->nro_orden_trabajo }} · {{ $shipment->routing_order }}</h1>
    </x-slot>

    <x-slot name="actions">
        <a href="{{ route('embarques.estado-pdf', $shipment) }}" class="fx-btn-secondary hidden md:inline-flex" title="Para enviar al cliente: sin costos ni datos internos">PDF de estado</a>
        @if ($canEditShipment)
            <a href="{{ route('embarques.edit', $shipment) }}" class="fx-btn-secondary">Editar</a>
        @endif
        @if ($canTrack && ! $isFinished)
            <a href="{{ route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking']) }}#registrar-hito" class="fx-btn-primary hidden sm:inline-flex">Registrar hito</a>
        @elseif ($canCommunicate)
            <a href="{{ route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking']) }}#nota-cliente" class="fx-btn-primary hidden sm:inline-flex">Nota al cliente</a>
        @endif
        @if ($canCommunicate)
            <a href="{{ route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking']) }}#whatsapp" class="fx-btn-secondary" title="Mensaje al cliente por WhatsApp, sin costo">WhatsApp</a>
        @endif
    </x-slot>

    <div
        x-data="{ showDocumentModal: {{ $documentFormHasErrors || ($initialTab === 'documentos' && request()->boolean('subir')) ? 'true' : 'false' }} }"
        class="space-y-4"
    >
        <p class="text-slate-600">
            <span class="font-medium text-slate-900">{{ $shipment->client->razon_social }}</span>
            · {{ $cargoLabel }} · {{ $route }}
            @if ($shipment->bl) · BL {{ $shipment->bl }} @endif
        </p>

        {{-- Datos clave: cada uno aparece solo aqui --}}
        <section class="fx-card grid grid-cols-2 gap-x-6 gap-y-4 p-4 sm:grid-cols-3 lg:grid-cols-6">
            <div class="col-span-2 sm:col-span-3 lg:col-span-2">
                <p class="text-xs text-slate-500">Estado</p>
                <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 font-semibold text-slate-900">
                    <span class="fx-status-dot {{ \App\Support\ChartPalette::stage($shipment->estado_actual)['dot'] }}" title="{{ \App\Support\ChartPalette::stage($shipment->estado_actual)['label'] }}"></span>
                    {{ $shipment->estado_actual }}
                    @unless ($isFinished)
                        <span class="font-normal text-slate-500">· paso {{ $currentStateIndex + 1 }} de {{ $stateCount }}</span>
                    @endunless
                </p>
                <div class="mt-1.5 h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-accent-600" style="width: {{ round(($currentStateIndex + 1) / $stateCount * 100) }}%"></div>
                </div>
            </div>
            <div>
                <p class="text-xs text-slate-500">ETD</p>
                <p class="mt-0.5 tabular-nums text-slate-900">{{ $shipment->fecha_etd?->format('d/m/Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">ETA</p>
                <p @class(['mt-0.5 tabular-nums', 'font-semibold text-rose-700' => $isDelayed, 'text-slate-900' => ! $isDelayed])>
                    {{ $shipment->fecha_eta?->format('d/m/Y') ?? '—' }}
                </p>
                @if ($etaDiffers && $user->isInternal())
                    <p class="text-xs text-amber-700">El cliente ve {{ $shipment->fecha_eta_cliente?->format('d/m/Y') ?? 'sin fecha' }}</p>
                @endif
            </div>
            <div>
                <p class="text-xs text-slate-500">Customer · Operador</p>
                <p class="mt-0.5 truncate text-slate-900">{{ $shipment->customerService?->name ?? '—' }} · {{ $shipment->operador?->name ?? 'sin asignar' }}</p>
            </div>
            @if ($canViewTrackingCode)
                <div x-data="{ copied: false }">
                    <p class="text-xs text-slate-500">Código para el cliente</p>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2">
                        <span class="rounded bg-yellow-300 px-2 py-0.5 font-mono font-semibold tracking-wide text-slate-900 ring-1 ring-yellow-400">{{ $shipment->tracking_code }}</span>
                        <button type="button" class="fx-btn-link-primary text-xs"
                                x-on:click="navigator.clipboard.writeText(@js($shipment->tracking_code)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                x-text="copied ? 'Copiado' : 'Copiar código'">Copiar código</button>
                    </div>
                </div>
            @endif
        </section>

        {{-- Requiere atencion: alertas de plazo y tareas, solo si hay algo --}}
        @if ($attention !== [] || $visibleAlerts->isNotEmpty())
            @php $hasDanger = collect($attention)->contains('level', 'danger') || $visibleAlerts->contains('level', 'danger'); @endphp
            <section @class([
                'fx-card border-l-4 p-4',
                'border-l-rose-600' => $hasDanger,
                'border-l-amber-500' => ! $hasDanger,
            ])>
                <h2 class="fx-section-title">Requiere atención</h2>
                <ul class="mt-2 divide-y divide-slate-100">
                    @foreach ($visibleAlerts as $alert)
                        @include('alerts.row', ['alert' => $alert, 'compact' => true])
                    @endforeach
                    @foreach ($attention as $item)
                        <li class="flex flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <p class="flex items-start gap-2 text-slate-700">
                                <span class="flex h-5 w-4 shrink-0 items-center justify-center">
                                    <span @class(['fx-status-dot', 'bg-rose-600' => $item['level'] === 'danger', 'bg-amber-500' => $item['level'] !== 'danger'])></span>
                                </span>
                                {{ $item['text'] }}
                            </p>
                            @if ($item['action_url'])
                                <a href="{{ $item['action_url'] }}" class="fx-btn-link-primary shrink-0 pl-4 sm:pl-0">{{ $item['action_label'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Pestanas --}}
        <nav class="-mx-4 overflow-x-auto border-b border-slate-200 px-4 sm:mx-0 sm:px-0" aria-label="Secciones del embarque">
            <div class="fx-tabs">
                @foreach ($tabs as $key => $tab)
                    <a href="{{ route('embarques.show', ['shipment' => $shipment, 'tab' => $key]) }}"
                       @class(['fx-tab', 'fx-tab-active' => $initialTab === $key])
                       @if ($initialTab === $key) aria-current="page" @endif>
                        <x-nav-icon :name="$tab['icon']" class="fx-tab-icon" />
                        {{ $tab['label'] }}
                        @if ($tab['badge'])
                            <span class="text-xs font-medium text-amber-700">{{ $tab['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </nav>

        <div>
            @if ($initialTab === 'datos')
                @include('shipments.tabs.datos')
            @elseif ($initialTab === 'tracking')
                @include('shipments.tabs.tracking')
            @elseif ($initialTab === 'documentos')
                @include('shipments.tabs.documentos')
            @elseif ($initialTab === 'finanzas' && $canSeeFinance)
                @include('shipments.tabs.finanzas')
            @endif
        </div>

        @if ($canManageDocuments && $initialTab === 'documentos')
            <template x-teleport="body">
                <div x-show="showDocumentModal" x-cloak x-on:keydown.escape.window="showDocumentModal = false"
                     class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-0 sm:items-center sm:p-4">
                    <section class="w-full max-w-lg rounded-t-md bg-white shadow-lg sm:rounded-md" x-on:click.outside="showDocumentModal = false"
                             role="dialog" aria-modal="true" aria-labelledby="document-modal-title">
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                            <h3 id="document-modal-title" class="fx-section-title">Subir documento</h3>
                            <button type="button" x-on:click="showDocumentModal = false" class="rounded p-1 text-slate-400 hover:text-slate-700" aria-label="Cerrar">
                                <x-nav-icon name="close" class="h-5 w-5" />
                            </button>
                        </div>
                        <form method="POST" action="{{ route('embarques.documentos.store', $shipment) }}" enctype="multipart/form-data" class="space-y-4 p-5" data-disable-native-autocomplete="true">
                            @csrf
                            <x-form-combobox
                                id="categoria"
                                name="categoria"
                                label="Categoría"
                                :options="$documentCategoryOptions"
                                :value="old('categoria', request('categoria', $documentCategories[0] ?? ''))"
                                placeholder="Seleccionar categoría"
                                required
                            />
                            <x-form-autocomplete
                                id="tipo_documento"
                                name="tipo_documento"
                                label="Tipo de documento"
                                :options="$documentTypeOptions"
                                :value="old('tipo_documento', request('tipo'))"
                                placeholder="Escribe o busca el tipo"
                                search-placeholder="Buscar tipo de documento..."
                                empty-text="No hay tipos sugeridos que coincidan."
                                required
                            />
                            <div>
                                <label class="fx-label" for="archivo">Archivo (PDF, JPG o PNG, máx. 5 MB)</label>
                                <input class="fx-input" id="archivo" name="archivo" type="file" accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" x-on:click="showDocumentModal = false" class="fx-btn-secondary">Cancelar</button>
                                <button class="fx-btn-primary" type="submit">Subir</button>
                            </div>
                        </form>
                    </section>
                </div>
            </template>
        @endif
    </div>
</x-app-layout>
