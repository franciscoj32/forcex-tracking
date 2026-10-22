{{-- Reportes: analisis por rango de fechas y exportacion. Mismo calculo que el Inicio. --}}
@use('App\Support\Money')
@php
    $query = $filters->query();
    $monthMax = max((int) $perMonth->max('total'), 1);
    $statusMax = max((int) $byStatus->max('total'), 1);
    $clientMax = max((int) $topClients->max('total'), 1);
    $cargo = \App\Support\ChartPalette::CARGO;
    $rankingMax = max((float) $ranking->max('margin'), 1);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1>Reportes</h1>
    </x-slot>

    <x-slot name="actions">
        <details class="relative" x-data x-on:click.outside="$el.removeAttribute('open')">
            <summary class="fx-btn-secondary cursor-pointer list-none">Exportar ▾</summary>
            <div class="absolute right-0 z-30 mt-1 w-64 overflow-hidden rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                <a href="{{ route('reportes.operaciones', $query) }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Excel de operaciones</a>
                @can('export-financial')
                    <a href="{{ route('reportes.financiero', $query) }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        {{ auth()->user()->isAdmin() ? 'Excel financiero por RO' : 'Excel financiero de mis RO' }}
                    </a>
                @endcan
                @can('view-management')
                    <a href="{{ route('reportes.gerencial', ['mes' => ($filters->to ?? now())->format('Y-m')]) }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        PDF gerencial de {{ ucfirst(($filters->to ?? now())->translatedFormat('F Y')) }}
                    </a>
                @endcan
            </div>
        </details>
    </x-slot>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('reportes.index') }}" class="fx-card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto] lg:items-end">
        <x-form-date-picker id="date_from" name="date_from" label="Desde" :value="$filters->from?->toDateString()" placeholder="Inicio" />
        <x-form-date-picker id="date_to" name="date_to" label="Hasta" :value="$filters->to?->toDateString()" placeholder="Fin" />
        <div>
            <label class="fx-label" for="client_id">Cliente</label>
            <select id="client_id" name="client_id" class="fx-select">
                <option value="">Todos</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected($filters->clientId === $client->id)>{{ $client->razon_social }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="fx-label" for="tipo_carga">Tipo</label>
            <select id="tipo_carga" name="tipo_carga" class="fx-select">
                <option value="">Todos</option>
                @foreach (['FCL' => 'FCL', 'LCL' => 'LCL', 'AEREO' => 'Aéreo'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters->cargoType === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if ($commercials->isNotEmpty())
            <div>
                <label class="fx-label" for="comercial_id">Comercial</label>
                <select id="comercial_id" name="comercial_id" class="fx-select">
                    <option value="">Todos</option>
                    @foreach ($commercials as $commercial)
                        <option value="{{ $commercial->id }}" @selected($filters->commercialId === $commercial->id)>{{ $commercial->name }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <div class="hidden lg:block"></div>
        @endif
        <div class="flex gap-2">
            <button class="fx-btn-primary" type="submit">Aplicar</button>
            <a href="{{ route('reportes.index') }}" class="fx-btn-secondary">Limpiar</a>
        </div>
    </form>

    <p class="mt-2 text-xs text-slate-500">Cada embarque cuenta en el mes de su ETD; si aún no tiene ETD, en el de su registro.</p>

    {{-- Numeros del rango --}}
    <section class="fx-card mt-4 grid grid-cols-2 gap-4 p-4 tabular-nums lg:grid-cols-4">
        <div>
            <p class="text-xs text-slate-500">Embarques</p>
            <p class="mt-0.5 text-2xl font-semibold text-slate-900">{{ $counts['total'] }}</p>
            @include('partials.cargo-mix', ['counts' => $counts])
        </div>
        <div>
            <p class="text-xs text-slate-500">Activos · retrasados</p>
            <p class="mt-0.5 text-2xl font-semibold text-slate-900">
                {{ $active }} <span @class(['text-base', 'text-rose-700' => $delayed > 0, 'text-slate-400' => $delayed === 0])>· {{ $delayed }}</span>
            </p>
            <p class="text-xs text-slate-500">{{ $counts['total'] - $active }} finalizados</p>
        </div>
        @if ($canSeeMoney)
            <div>
                <p class="text-xs text-slate-500">Margen</p>
                <p @class(['mt-0.5 text-2xl font-semibold', 'text-rose-700' => $totals['margin'] < 0, 'text-slate-900' => $totals['margin'] >= 0])>{{ Money::format($totals['margin']) }}</p>
                <p class="text-xs text-slate-500">
                    Venta {{ Money::format($totals['sales']) }}{{ $totals['margin_percent'] !== null ? ' · '.$totals['margin_percent'].'%' : '' }}
                    @if ($totals['incomplete'] > 0)
                        · <span class="text-amber-700">{{ $totals['incomplete'] }} sin tipo de cambio</span>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Por cobrar al cliente</p>
                <p class="mt-0.5 text-2xl font-semibold text-slate-900">{{ Money::format($receivable) }}</p>
                <p class="text-xs text-slate-500">Facturas pendientes o emitidas</p>
            </div>
        @endif
    </section>

    {{-- Graficos simples, un solo color --}}
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="fx-card lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <h2 class="fx-section-title">Embarques por mes</h2>
                <div class="flex flex-wrap gap-4 text-xs text-slate-600">
                    @foreach ($cargo as $type)
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm {{ $type['dot'] }}"></span>{{ $type['label'] }}</span>
                    @endforeach
                </div>
            </div>
            <div class="flex h-56 items-end gap-2 overflow-x-auto px-4 pb-4 pt-6">
                @foreach ($perMonth as $point)
                    @php $height = $point['total'] > 0 ? max(4, round(($point['total'] / $monthMax) * 100)) : 0; @endphp
                    <div class="flex h-full min-w-10 flex-1 flex-col items-center justify-end gap-1.5">
                        <span class="text-xs font-medium tabular-nums text-slate-700">{{ $point['total'] }}</span>
                        <div class="flex w-full max-w-12 flex-col-reverse overflow-hidden rounded-t" style="height: {{ $height }}%"
                             title="{{ collect($point['types'])->map(fn ($n, $t) => $cargo[$t]['label'].': '.$n)->implode(' · ') }}">
                            @foreach ($point['types'] as $type => $count)
                                @if ($count > 0)
                                    <div class="{{ $cargo[$type]['bar'] }}" style="height: {{ round($count / $point['total'] * 100, 2) }}%"></div>
                                @endif
                            @endforeach
                        </div>
                        @if ($point['total'] === 0)
                            <div class="h-px w-full max-w-12 bg-slate-200"></div>
                        @endif
                        <span class="whitespace-nowrap text-xs text-slate-500">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="fx-card">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Por estado actual</h2>
            <div class="p-4">
                @php $stageTotal = max((int) $byStage->sum('total'), 1); @endphp
                <div class="flex h-3 overflow-hidden rounded-full bg-slate-100">
                    @foreach ($byStage as $stage)
                        @if ($stage['total'] > 0)
                            <div class="{{ $stage['bar'] }}" style="width: {{ round($stage['total'] / $stageTotal * 100, 2) }}%" title="{{ $stage['label'] }}: {{ $stage['total'] }}"></div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                    @foreach ($byStage as $stage)
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm {{ $stage['dot'] }}"></span>{{ $stage['label'] }} <span class="tabular-nums text-slate-900">{{ $stage['total'] }}</span></span>
                    @endforeach
                </div>

                <div class="mt-4 space-y-2.5">
                    @forelse ($byStatus as $row)
                        <div class="grid grid-cols-[9rem_minmax(0,1fr)_2.5rem] items-center gap-3 text-sm">
                            <span class="truncate text-slate-700">{{ $row['label'] }}</span>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $row['stage']['bar'] }}" style="width: {{ round(($row['total'] / $statusMax) * 100) }}%"></div>
                            </div>
                            <span class="text-right tabular-nums text-slate-900">{{ $row['total'] }}</span>
                        </div>
                    @empty
                        <p class="text-slate-500">Sin embarques en el rango.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="fx-card">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Clientes con más embarques</h2>
            <div class="space-y-2.5 p-4">
                @forelse ($topClients as $row)
                    <div @class(['grid items-center gap-3 text-sm', 'grid-cols-[minmax(0,10rem)_minmax(0,1fr)_2.5rem_6rem]' => $canSeeMoney, 'grid-cols-[minmax(0,10rem)_minmax(0,1fr)_2.5rem]' => ! $canSeeMoney])>
                        <span class="flex min-w-0 items-center gap-2 text-slate-700" title="{{ $row['label'] }}">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm {{ \App\Support\ChartPalette::series($loop->index) }}"></span>
                            <span class="truncate">{{ $row['label'] }}</span>
                        </span>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full {{ \App\Support\ChartPalette::series($loop->index) }}" style="width: {{ round(($row['total'] / $clientMax) * 100) }}%"></div>
                        </div>
                        <span class="text-right tabular-nums text-slate-900">{{ $row['total'] }}</span>
                        @if ($canSeeMoney)
                            <span class="text-right text-xs tabular-nums text-slate-500">{{ Money::format($row['margin']) }}</span>
                        @endif
                    </div>
                @empty
                    <p class="text-slate-500">Sin embarques en el rango.</p>
                @endforelse
            </div>
        </section>

        @if ($canSeeMoney)
            <section class="fx-card lg:col-span-2">
                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Ranking de comerciales del rango</h2>
                @if ($ranking->isEmpty())
                    <p class="px-4 py-4 text-slate-500">Sin embarques en el rango.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="fx-table">
                            <thead>
                                <tr>
                                    <th class="fx-th w-10">#</th>
                                    <th class="fx-th">Comercial</th>
                                    <th class="fx-th text-right">RO</th>
                                    <th class="fx-th text-right">Venta</th>
                                    <th class="fx-th text-right">Margen</th>
                                    <th class="fx-th text-right">%</th>
                                    <th class="fx-th"><span class="sr-only">Barra</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($ranking as $row)
                                    <tr>
                                        <td class="fx-td text-slate-500">{{ $row['position'] }}</td>
                                        <td class="fx-td text-slate-900">{{ $row['name'] }}</td>
                                        <td class="fx-td text-right">{{ $row['shipments'] }}</td>
                                        <td class="fx-td whitespace-nowrap text-right">{{ Money::format($row['sales']) }}</td>
                                        <td @class(['fx-td whitespace-nowrap text-right font-medium', 'text-rose-700' => $row['margin'] < 0, 'text-slate-900' => $row['margin'] >= 0])>{{ Money::format($row['margin']) }}</td>
                                        <td class="fx-td text-right text-slate-500">{{ $row['margin_percent'] !== null ? $row['margin_percent'].'%' : '—' }}</td>
                                        <td class="fx-td w-40">
                                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                                <div class="h-full rounded-full {{ \App\Support\ChartPalette::series($loop->index) }}" style="width: {{ max(0, round($row['margin'] / $rankingMax * 100)) }}%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
