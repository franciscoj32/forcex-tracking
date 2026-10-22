@php
    $viewer = auth()->user();
    $clientOptions = $clients
        ->map(fn ($client) => ['value' => (string) $client->id, 'label' => $client->razon_social])
        ->values();
    $hasFilters = $filters['q'] !== '' || $filters['client_id'] !== '' || $filters['tipo'] !== '' || $filters['mios'];
    // Columna de responsable: cada rol ve a la persona con la que coordina.
    $peerLabel = $viewer->isCustomer() ? 'Operador' : 'Customer';
    $stateCount = count(\App\Models\Shipment::TRACKING_STATES);
    $statesBeforeArrival = \App\Models\Shipment::statesBeforeArrival();
    $today = now()->startOfDay();
    $queryFor = fn (array $changes) => array_filter(array_merge(request()->except('page'), $changes), fn ($value) => $value !== null && $value !== '');
    $viewIcons = [
        'activos' => 'activity',
        'retrasados' => 'alert',
        'arribos' => 'arrival',
        'validar' => 'calendar-check',
        'finalizados' => 'check-circle',
        'todos' => 'list',
    ];
    $gridCols = 'md:grid md:grid-cols-[2rem_minmax(0,8rem)_minmax(0,1.3fr)_3.5rem_minmax(0,1.2fr)_8.5rem_minmax(0,10rem)_minmax(0,7rem)] md:items-center md:gap-3';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1>Embarques</h1>
    </x-slot>

    <x-slot name="actions">
        @can('bulk-update-shipments')
            <a href="{{ route('embarques.masivo') }}" class="fx-btn-secondary">Actualización masiva</a>
        @endcan
        <a href="{{ route('embarques.export', request()->query()) }}" class="fx-btn-secondary hidden sm:inline-flex">Excel</a>
        @can('create', \App\Models\Shipment::class)
            <a href="{{ route('embarques.create') }}" class="fx-btn-primary">Nueva RO</a>
        @endcan
    </x-slot>

    {{-- Vistas rapidas --}}
    <nav class="-mx-4 overflow-x-auto border-b border-slate-200 px-4 sm:mx-0 sm:px-0" aria-label="Vistas">
        <div class="fx-tabs">
            @foreach ($views as $key => $label)
                @php
                    $isCurrent = $filters['vista'] === $key;
                    $alert = in_array($key, ['retrasados', 'validar'], true) && $counts[$key] > 0;
                @endphp
                <a href="{{ route('embarques.index', $queryFor(['vista' => $key])) }}"
                   @class(['fx-tab', 'fx-tab-active' => $isCurrent])
                   @if ($isCurrent) aria-current="page" @endif>
                    <x-nav-icon :name="$viewIcons[$key] ?? 'default'" class="fx-tab-icon" />
                    {{ $label }}
                    <span @class([
                        'tabular-nums',
                        'font-semibold text-rose-700' => $alert,
                        'text-slate-400' => ! $alert && ! $isCurrent,
                    ])>{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    {{-- Filtros: se aplican al cambiar --}}
    <form method="GET" action="{{ route('embarques.index') }}" class="mt-4 flex flex-wrap items-center gap-2" x-data>
        <input type="hidden" name="vista" value="{{ $filters['vista'] }}">

        <label for="q" class="sr-only">Buscar</label>
        <input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar OT, RO, BL, nave o código"
               class="fx-input mt-0 w-full sm:w-64">

        @if ($clientOptions->isNotEmpty())
            <div
                x-data="filterCombobox({
                    options: {{ Illuminate\Support\Js::from($clientOptions) }},
                    value: {{ Illuminate\Support\Js::from($filters['client_id']) }},
                    placeholder: 'Cliente',
                    allLabel: 'Todos los clientes',
                    searchPlaceholder: 'Buscar cliente...',
                    emptyText: 'No hay clientes que coincidan.',
                    searchEnabled: true,
                })"
                x-init="$watch('value', () => $nextTick(() => $el.closest('form').submit()))"
                x-on:click.outside="closePanel()"
                x-on:keydown.escape.prevent.stop="closePanel()"
                x-on:keydown.arrow-down.prevent.stop="move(1)"
                x-on:keydown.arrow-up.prevent.stop="move(-1)"
                x-on:keydown.enter.prevent.stop="confirmActive()"
                class="fx-combobox w-full sm:w-56"
            >
                <input type="hidden" name="client_id" x-model="value">
                <button type="button" class="fx-combobox-trigger" :data-open="open" x-on:click="toggle()" aria-label="Filtrar por cliente">
                    <span class="fx-combobox-trigger-label" x-text="buttonLabel"></span>
                    <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/></svg>
                </button>
                <div x-cloak x-show="open" class="fx-combobox-panel">
                    <div class="fx-combobox-search">
                        <input x-ref="searchInput" x-model="query" type="search" placeholder="Buscar cliente..." class="fx-combobox-search-input">
                    </div>
                    <div class="fx-combobox-list">
                        <button type="button" class="fx-combobox-option" :data-selected="value === ''" x-on:click="clear()">Todos los clientes</button>
                        <template x-for="(option, index) in filteredOptions" :key="option.value">
                            <button type="button" x-ref="optionNodes" class="fx-combobox-option"
                                    :data-active="activeIndex === index" :data-selected="value === String(option.value)"
                                    x-on:click="choose(option)" x-text="option.label"></button>
                        </template>
                        <p x-show="filteredOptions.length === 0" class="fx-combobox-empty" x-text="emptyText"></p>
                    </div>
                </div>
            </div>
        @endif

        <label for="tipo" class="sr-only">Tipo de carga</label>
        <select id="tipo" name="tipo" class="fx-select mt-0 w-auto" onchange="this.form.submit()">
            <option value="">Todos los tipos</option>
            @foreach (['FCL' => 'FCL', 'LCL' => 'LCL', 'AEREO' => 'Aéreo'] as $value => $label)
                <option value="{{ $value }}" @selected($filters['tipo'] === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <label class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700">
            <input type="checkbox" name="mios" value="1" @checked($filters['mios']) onchange="this.form.submit()"
                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/30">
            Solo míos
        </label>

        <button type="submit" class="sr-only">Buscar</button>

        @if ($hasFilters)
            <a href="{{ route('embarques.index', ['vista' => $filters['vista']]) }}" class="fx-btn-link-primary px-2">Limpiar filtros</a>
        @endif
    </form>

    {{-- Listado: tabla en escritorio, tarjetas en celular --}}
    <div class="mt-4 overflow-hidden rounded-md border border-slate-200 bg-white">
        <div class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-medium text-slate-500 {{ $gridCols }}">
            <span class="sr-only">Fijar</span>
            <span>OT / RO</span>
            <span>Cliente</span>
            <span>Tipo</span>
            <span>Ruta</span>
            <span>ETA</span>
            <span>Estado</span>
            <span>{{ $peerLabel }}</span>
        </div>

        <ul class="divide-y divide-slate-100">
            @forelse ($shipments as $shipment)
                @php
                    $isPinned = (bool) ($shipment->is_pinned_for_user ?? false);
                    $url = route('embarques.show', $shipment);
                    $eta = $shipment->fecha_eta;
                    $isFinished = $shipment->estado_actual === 'Finalizado';
                    $beforeArrival = in_array($shipment->estado_actual, $statesBeforeArrival, true);
                    $etaDays = $eta ? (int) $today->diffInDays($eta->copy()->startOfDay(), false) : null;
                    $isDelayed = $eta && $beforeArrival && $etaDays < 0;
                    $etaHint = match (true) {
                        $eta === null || ! $beforeArrival => null,
                        $isDelayed => 'vencida hace '.abs($etaDays).' '.(abs($etaDays) === 1 ? 'día' : 'días'),
                        $etaDays === 0 => 'hoy',
                        $etaDays === 1 => 'mañana',
                        $etaDays <= 14 => "en {$etaDays} días",
                        default => null,
                    };
                    $step = $shipment->currentTrackingStateIndex() + 1;
                    $peer = $viewer->isCustomer() ? $shipment->operador : $shipment->customerService;
                    $route = trim(($shipment->puerto_carga ?: $shipment->origen).' → '.($shipment->puerto_descarga ?: $shipment->destino_final));
                @endphp
                <li
                    x-data
                    x-on:click="if (! $event.target.closest('a,button,form,input')) window.location = @js($url)"
                    class="cursor-pointer px-4 py-3 transition-colors hover:bg-slate-50 {{ $gridCols }}"
                >
                    {{-- Fijar --}}
                    <form method="POST" action="{{ route('embarques.pin', $shipment) }}" class="float-right ml-2 md:float-none md:ml-0">
                        @csrf
                        <button type="submit"
                                class="flex h-8 w-8 items-center justify-center rounded-md hover:bg-slate-100 {{ $isPinned ? 'text-amber-500' : 'text-slate-300 hover:text-slate-500' }}"
                                title="{{ $isPinned ? 'Quitar de fijados' : 'Fijar arriba' }}"
                                aria-label="{{ $isPinned ? 'Quitar de fijados' : 'Fijar arriba' }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="{{ $isPinned ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>
                            </svg>
                            @if ($isPinned)<span class="sr-only">Fijado</span>@endif
                        </button>
                    </form>

                    {{-- OT / RO --}}
                    <div class="min-w-0">
                        <a href="{{ $url }}" class="font-semibold text-slate-900 hover:underline">{{ $shipment->nro_orden_trabajo }}</a>
                        <p class="truncate text-xs text-slate-500">{{ $shipment->routing_order }}</p>
                    </div>

                    <p class="mt-1 truncate text-slate-700 md:mt-0">{{ $shipment->client->razon_social }}</p>

                    <p class="hidden text-slate-700 md:block">{{ $shipment->tipo_carga === 'AEREO' ? 'Aéreo' : $shipment->tipo_carga }}</p>

                    <p class="truncate text-slate-600">
                        <span class="md:hidden">{{ $shipment->tipo_carga === 'AEREO' ? 'Aéreo' : $shipment->tipo_carga }} · </span>{{ $route }}
                    </p>

                    {{-- ETA --}}
                    <div class="mt-2 flex items-baseline gap-2 md:mt-0 md:block">
                        <span class="text-xs text-slate-500 md:hidden">ETA</span>
                        <p @class(['tabular-nums', 'font-semibold text-rose-700' => $isDelayed, 'text-slate-700' => ! $isDelayed])>
                            {{ $eta?->format('d/m/Y') ?? '—' }}
                        </p>
                        @if ($etaHint)
                            <p @class(['text-xs', 'text-rose-700' => $isDelayed, 'text-slate-500' => ! $isDelayed])>{{ $etaHint }}</p>
                        @endif
                        @if ($shipment->pending_date_changes_count > 0)
                            <span class="fx-badge mt-0.5 bg-amber-100 text-amber-800">Fecha por validar</span>
                        @endif
                    </div>

                    {{-- Estado --}}
                    <div class="mt-2 md:mt-0">
                        <p class="flex items-center gap-1.5 truncate text-slate-800" title="{{ \App\Support\ChartPalette::stage($shipment->estado_actual)['label'] }}">
                            <span class="fx-status-dot {{ \App\Support\ChartPalette::stage($shipment->estado_actual)['dot'] }}"></span>
                            <span class="truncate">{{ $shipment->estado_actual }}</span>
                        </p>
                        @unless ($isFinished)
                            <div class="mt-1 h-1 w-full max-w-[9rem] overflow-hidden rounded-full bg-slate-200" title="Paso {{ $step }} de {{ $stateCount }}">
                                <div class="h-full rounded-full bg-accent-600" style="width: {{ round($step / $stateCount * 100) }}%"></div>
                            </div>
                        @endunless
                    </div>

                    <p class="hidden truncate text-slate-600 md:block">{{ $peer?->name ?? 'Sin asignar' }}</p>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-slate-500">Sin embarques en esta vista.</li>
            @endforelse
        </ul>
    </div>

    @if ($shipments->hasPages())
        <div class="mt-4">{{ $shipments->links() }}</div>
    @endif
</x-app-layout>
