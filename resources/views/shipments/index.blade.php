@php
    $showTrackingCode = auth()->user()->hasRole([\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_OPERACIONES, \App\Models\User::ROLE_COMERCIAL]);
    $selectedClientName = $clients->firstWhere('id', (int) $selectedClientId)?->razon_social;
    $clientOptions = $clients
        ->map(fn ($client) => ['value' => (string) $client->id, 'label' => $client->razon_social])
        ->values();
    $lifecycleOptions = collect([
        ['value' => 'all', 'label' => 'Todos los ciclos'],
        ['value' => 'active', 'label' => 'Solo activos'],
        ['value' => 'finished', 'label' => 'Solo finalizados'],
    ]);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Embarques</h1>
        <p class="text-sm text-slate-500">Consulta, prioriza y exporta cargas internacionales segun tus filtros actuales.</p>
    </x-slot>

    <div class="fx-card overflow-hidden">
        <div class="fx-card-header items-start">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Listado de embarques</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Consulta y exporta embarques por cliente, ciclo o referencia operativa.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if (auth()->user()->hasRole(['admin', 'operaciones']))
                    <a href="{{ route('embarques.create') }}" class="inline-flex items-center justify-center rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-500">
                        Nuevo embarque
                    </a>
                @endif
                <a
                    href="{{ route('embarques.export', request()->query()) }}"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"
                >
                    Exportar Excel
                </a>
            </div>
        </div>

        <div class="px-6 py-4">
            <div class="flex flex-col gap-4">
                        <form method="GET" action="{{ route('embarques.index') }}" class="grid gap-3">
                            <div class="flex items-center gap-3">
                                <input
                                    type="search"
                                    name="q"
                                    value="{{ $search }}"
                                    placeholder="Buscar OT, BL o Routing"
                                    class="fx-input mt-0 min-w-0 flex-1 basis-0"
                                >
                                @if ($clients->isNotEmpty())
                                    <div
                                        x-data="filterCombobox({
                                            options: {{ Illuminate\Support\Js::from($clientOptions) }},
                                            value: {{ Illuminate\Support\Js::from($selectedClientId) }},
                                            placeholder: 'Cliente',
                                            allLabel: 'Todos los clientes',
                                            searchPlaceholder: 'Buscar cliente...',
                                            emptyText: 'No hay clientes que coincidan.',
                                            searchEnabled: true,
                                        })"
                                        x-on:click.outside="closePanel()"
                                        x-on:keydown.escape.prevent.stop="closePanel()"
                                        x-on:keydown.arrow-down.prevent.stop="move(1)"
                                        x-on:keydown.arrow-up.prevent.stop="move(-1)"
                                        x-on:keydown.enter.prevent.stop="confirmActive()"
                                        class="fx-combobox min-w-0 flex-1 basis-0"
                                    >
                                        <input type="hidden" name="client_id" x-model="value">
                                        <button type="button" class="fx-combobox-trigger" :data-open="open" x-on:click="toggle()">
                                            <span class="fx-combobox-trigger-label" x-text="buttonLabel"></span>
                                            <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="m7 15 5 5 5-5"></path>
                                                <path d="m7 9 5-5 5 5"></path>
                                            </svg>
                                        </button>

                                        <div x-cloak x-show="open" x-transition.origin.top.left class="fx-combobox-panel">
                                            <div class="fx-combobox-search">
                                                <input
                                                    x-ref="searchInput"
                                                    x-model="query"
                                                    type="search"
                                                    placeholder="Buscar cliente..."
                                                    class="fx-combobox-search-input"
                                                >
                                            </div>
                                            <div class="fx-combobox-list">
                                                <button
                                                    type="button"
                                                    class="fx-combobox-option"
                                                    :data-active="activeIndex === -1 && value === ''"
                                                    :data-selected="value === ''"
                                                    x-on:click="clear()"
                                                >
                                                    <span>Todos los clientes</span>
                                                    <svg x-show="value === ''" class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M20 6 9 17l-5-5"></path>
                                                    </svg>
                                                </button>

                                                <template x-if="filteredOptions.length === 0">
                                                    <div class="fx-combobox-empty">No hay clientes que coincidan.</div>
                                                </template>

                                                <template x-for="(option, index) in filteredOptions" :key="option.value">
                                                    <button
                                                        type="button"
                                                        class="fx-combobox-option"
                                                        x-ref="optionNodes"
                                                        :data-active="activeIndex === index"
                                                        :data-selected="value === option.value"
                                                        x-on:mousemove="activeIndex = index"
                                                        x-on:click="choose(option)"
                                                    >
                                                        <span class="truncate" x-text="option.label"></span>
                                                        <svg x-show="value === option.value" class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <path d="M20 6 9 17l-5-5"></path>
                                                        </svg>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <div
                                    x-data="filterCombobox({
                                        options: {{ Illuminate\Support\Js::from($lifecycleOptions) }},
                                        value: {{ Illuminate\Support\Js::from($selectedLifecycle) }},
                                        placeholder: 'Ciclo',
                                        allLabel: 'Todos los ciclos',
                                        emptyText: 'No hay ciclos disponibles.',
                                        searchEnabled: false,
                                    })"
                                    x-on:click.outside="closePanel()"
                                    x-on:keydown.escape.prevent.stop="closePanel()"
                                    x-on:keydown.arrow-down.prevent.stop="move(1)"
                                    x-on:keydown.arrow-up.prevent.stop="move(-1)"
                                    x-on:keydown.enter.prevent.stop="confirmActive()"
                                    class="fx-combobox min-w-0 flex-1 basis-0"
                                >
                                    <input type="hidden" name="lifecycle" x-model="value">
                                    <button type="button" class="fx-combobox-trigger" :data-open="open" x-on:click="toggle()">
                                        <span class="fx-combobox-trigger-label" x-text="buttonLabel"></span>
                                        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="m7 15 5 5 5-5"></path>
                                            <path d="m7 9 5-5 5 5"></path>
                                        </svg>
                                    </button>

                                    <div x-cloak x-show="open" x-transition.origin.top.left class="fx-combobox-panel">
                                        <div class="fx-combobox-list">
                                            <template x-for="(option, index) in filteredOptions" :key="option.value">
                                                <button
                                                    type="button"
                                                    class="fx-combobox-option"
                                                    x-ref="optionNodes"
                                                    :data-active="activeIndex === index"
                                                    :data-selected="value === option.value"
                                                    x-on:mousemove="activeIndex = index"
                                                    x-on:click="choose(option)"
                                                >
                                                    <span class="truncate" x-text="option.label"></span>
                                                    <svg x-show="value === option.value" class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M20 6 9 17l-5-5"></path>
                                                    </svg>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="inline-flex h-[46px] min-w-[132px] shrink-0 items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500">
                                    Filtrar
                                </button>
                                @if ($search !== '' || $selectedClientId !== '' || $selectedLifecycle !== 'all')
                                    <a href="{{ route('embarques.index') }}" class="fx-btn-secondary shrink-0 justify-center">Limpiar</a>
                                @endif
                            </div>
                        </form>

                        <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 text-xs font-medium text-slate-500">
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">Busqueda: {{ $search !== '' ? $search : 'Todas' }}</span>
                            @if ($selectedClientId !== '' && $selectedClientName)
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">Cliente: {{ $selectedClientName }}</span>
                            @endif
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">
                                Ciclo:
                                {{ $selectedLifecycle === 'active' ? 'Activos' : ($selectedLifecycle === 'finished' ? 'Finalizados' : 'Todos') }}
                            </span>
                        </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="fx-table w-full text-[13px]">
                <thead>
                    <tr>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5 text-center">
                            <span class="inline-flex items-center justify-center" title="Anclar">
                                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 3v10"/>
                                    <path d="M8 7h8"/>
                                    <path d="M7 13a5 5 0 0 0 10 0"/>
                                    <path d="M12 13v8"/>
                                </svg>
                            </span>
                        </th>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5">OT</th>
                        <th class="fx-th px-3 py-2.5">Cliente</th>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5">BL</th>
                        @if ($showTrackingCode)
                            <th class="fx-th whitespace-nowrap px-3 py-2.5">Seguimiento</th>
                        @endif
                        <th class="fx-th whitespace-nowrap px-3 py-2.5">Tipo</th>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5">ETA</th>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5">Estado</th>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($shipments as $shipment)
                        @php
                            $isPinned = (bool) ($shipment->is_pinned_for_user ?? false);
                            $rowClass = $isPinned
                                ? 'bg-amber-50'
                                : ($loop->odd ? 'bg-sky-50/60' : 'bg-emerald-50/50');
                            $statusBadgeClass = match (strtolower($shipment->estado_actual)) {
                                'finalizado', 'entregado', 'retiro de almacen', 'retiro de almacén' => 'bg-emerald-100 text-emerald-700',
                                'descarga', 'arribo a puerto', 'eta', 'transmision aduanas', 'transmisión aduanas', 'en transito', 'en tránsito', 'booking confirmado', 'atd' => 'bg-blue-100 text-blue-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="fx-td px-3 py-3 text-center">
                                <form method="POST" action="{{ route('embarques.pin', $shipment) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="inline-flex h-8 w-8 items-center justify-center text-slate-400 transition hover:text-cyan-600 {{ $isPinned ? 'text-cyan-600' : '' }}"
                                        title="{{ $isPinned ? 'Quitar anclado' : 'Anclar embarque' }}"
                                        aria-label="{{ $isPinned ? 'Quitar anclado' : 'Anclar embarque' }}"
                                    >
                                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 3v10"/>
                                            <path d="M8 7h8"/>
                                            <path d="M7 13a5 5 0 0 0 10 0"/>
                                            <path d="M12 13v8"/>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                            <td class="fx-td whitespace-nowrap px-3 py-3 font-semibold text-slate-900">
                                <div class="flex items-center gap-1.5">
                                    <span>{{ $shipment->nro_orden_trabajo }}</span>
                                    @if ($isPinned)
                                        <span class="inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-rose-700">Fijado</span>
                                    @endif
                                </div>
                            </td>
                            <td class="fx-td px-3 py-3">{{ $shipment->client->razon_social }}</td>
                            <td class="fx-td whitespace-nowrap px-3 py-3">{{ $shipment->bl }}</td>
                            @if ($showTrackingCode)
                                <td class="fx-td whitespace-nowrap px-3 py-3">
                                    <span class="rounded-lg bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-700">
                                        {{ $shipment->tracking_code }}
                                    </span>
                                </td>
                            @endif
                            <td class="fx-td whitespace-nowrap px-3 py-3">{{ $shipment->tipo_carga }}</td>
                            <td class="fx-td whitespace-nowrap px-3 py-3">{{ optional($shipment->fecha_eta)->format('d/m/Y') ?: '-' }}</td>
                            <td class="fx-td whitespace-nowrap px-3 py-3">
                                <span class="fx-badge {{ $statusBadgeClass }}">
                                    {{ $shipment->estado_actual }}
                                </span>
                            </td>
                            <td class="fx-td whitespace-nowrap px-3 py-3">
                                <div class="flex justify-end">
                                    <a
                                        href="{{ route('embarques.show', $shipment) }}"
                                        class="inline-flex min-h-[42px] min-w-[76px] items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500"
                                    >
                                        Ver
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showTrackingCode ? 9 : 8 }}" class="fx-td py-10 text-center text-slate-500">
                                No hay embarques registrados para este filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">{{ $shipments->links() }}</div>
    </div>
</x-app-layout>
