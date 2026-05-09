@php
    $statusMax = max((int) ($shipmentsByStatus->max('total') ?? 0), 1);
    $clientMax = max((int) ($shipmentsByClient->max('total') ?? 0), 1);
    $cargoTotal = max((int) $shipmentsByCargoType->sum('total'), 1);
    $financeMax = max((float) $financialTotals['provisions'], (float) $financialTotals['invoices'], 1);
    $clientOptions = $clients
        ->map(fn ($client) => ['value' => (string) $client->id, 'label' => $client->razon_social])
        ->values();

    $timelinePoints = $shipmentsTimeline->values()->map(function ($point, $index) use ($shipmentsTimeline) {
        $max = max((int) ($shipmentsTimeline->max('total') ?? 0), 1);
        $count = max($shipmentsTimeline->count(), 1);
        $x = $count === 1 ? 240 : 28 + (($index / ($count - 1)) * 424);
        $y = 108 - (($point['total'] / $max) * 64);

        return [
            'x' => round($x, 2),
            'y' => round($y, 2),
            'label' => $point['short_label'],
            'total' => $point['total'],
            'is_current' => $point['is_current'],
            'month_key' => $point['month_key'],
        ];
    });

    $timelinePolyline = $timelinePoints->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');
    $currentTimelinePoint = $timelinePoints->firstWhere('is_current', true);

    $donutCircumference = 2 * pi() * 42;
    $donutColors = ['#0891b2', '#0f766e', '#2563eb', '#f59e0b', '#7c3aed'];
    $donutOffset = 0;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Reportes ejecutivos</h1>
        <p class="text-sm text-slate-500">Lectura rapida de operacion, clientes y pulso financiero sobre la data actual.</p>
    </x-slot>

    <div class="space-y-5">
        <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-100/70">
            <div class="border-b border-slate-200 bg-[linear-gradient(135deg,rgba(248,250,252,0.98),rgba(240,249,255,0.94))] px-5 py-4">
                <form method="GET" action="{{ route('reportes.index') }}" class="grid gap-3 xl:grid-cols-[1.2fr_0.85fr_0.85fr_auto] xl:items-end">
                    <div class="space-y-1">
                        <label class="fx-label" for="client_id">Cliente</label>
                        <select id="client_id" name="client_id" class="fx-select mt-0">
                            <option value="">Todos los clientes</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected($filters['client_id'] == $client->id)>{{ $client->razon_social }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-form-date-picker
                        id="date_from"
                        name="date_from"
                        label="Desde ETD"
                        :value="$filters['date_from']"
                        placeholder="Inicio"
                    />
                    <x-form-date-picker
                        id="date_to"
                        name="date_to"
                        label="Hasta ETD"
                        :value="$filters['date_to']"
                        placeholder="Fin"
                    />
                    <div class="flex flex-wrap gap-3 xl:justify-end">
                        <button class="fx-btn-primary justify-center" type="submit">Aplicar filtros</button>
                        <a href="{{ route('reportes.index') }}" class="fx-btn-secondary justify-center">Limpiar</a>
                    </div>
                </form>
            </div>

            @if ($filters['has_filters'])
                <div class="flex flex-wrap items-center gap-2 px-5 py-3 text-sm text-slate-600">
                    <span class="font-semibold text-slate-900">Vista filtrada:</span>
                    @if ($filters['client_name'])
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">Cliente: {{ $filters['client_name'] }}</span>
                    @endif
                    @if ($filters['date_from'])
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">Desde: {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }}</span>
                    @endif
                    @if ($filters['date_to'])
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">Hasta: {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}</span>
                    @endif
                </div>
            @endif
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-3xl border border-slate-200 bg-white px-5 py-6 shadow-sm shadow-slate-100/70">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Embarques totales</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $summary['total_shipments'] }}</p>
            </article>
            <article class="rounded-3xl border border-cyan-200 bg-cyan-50/70 px-5 py-6 shadow-sm shadow-cyan-100/50">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-700">Activos</p>
                <p class="mt-3 text-3xl font-semibold text-cyan-900">{{ $summary['active_shipments'] }}</p>
            </article>
            <article class="rounded-3xl border border-emerald-200 bg-emerald-50/70 px-5 py-6 shadow-sm shadow-emerald-100/50">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-700">Finalizados</p>
                <p class="mt-3 text-3xl font-semibold text-emerald-900">{{ $summary['finished_shipments'] }}</p>
            </article>
            <article class="rounded-3xl border border-amber-200 bg-amber-50/80 px-5 py-6 shadow-sm shadow-amber-100/50">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-700">Clientes activos</p>
                <p class="mt-3 text-3xl font-semibold text-amber-900">{{ $summary['clients_count'] }}</p>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-[0.6fr_0.4fr]">
            <article class="rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-100/70">
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Tendencia por ETD</h2>
                            <p class="mt-1 text-sm text-slate-500">Linea continua por mes, extendida hasta el periodo actual.</p>
                        </div>
                        <div class="rounded-2xl border border-cyan-200 bg-cyan-50/70 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-700">Mes actual</p>
                            <div class="mt-2 flex items-end gap-3">
                                <p class="text-3xl font-semibold text-cyan-900">{{ $currentTimelinePoint['total'] ?? 0 }}</p>
                                <p class="pb-1 text-sm font-medium text-cyan-700">{{ now()->translatedFormat('M Y') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-4">
                    @if ($timelinePoints->isNotEmpty())
                        <div class="rounded-3xl border border-slate-100 bg-slate-50/70 p-4">
                            <svg viewBox="0 0 480 148" class="h-[210px] w-full">
                                <line x1="28" y1="108" x2="452" y2="108" stroke="#cbd5e1" stroke-width="1.5" stroke-dasharray="4 4"></line>
                                <polyline points="{{ $timelinePolyline }}" fill="none" stroke="#0891b2" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>
                                @foreach ($timelinePoints as $point)
                                    @if ($point['is_current'])
                                        <line x1="{{ $point['x'] }}" y1="24" x2="{{ $point['x'] }}" y2="118" stroke="#22c55e" stroke-width="1.5" stroke-dasharray="4 4"></line>
                                    @endif
                                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="{{ $point['is_current'] ? 6 : 4.5 }}" fill="#ffffff" stroke="{{ $point['is_current'] ? '#16a34a' : '#0891b2' }}" stroke-width="{{ $point['is_current'] ? 3 : 2 }}"></circle>
                                    <text x="{{ $point['x'] }}" y="128" text-anchor="middle" class="fill-slate-500 text-[10px] font-semibold">{{ $point['label'] }}</text>
                                    <text x="{{ $point['x'] }}" y="{{ $point['y'] - 11 }}" text-anchor="middle" class="fill-slate-900 text-[10px] font-semibold">{{ $point['total'] }}</text>
                                    @if ($point['is_current'])
                                        <text x="{{ $point['x'] }}" y="18" text-anchor="middle" class="fill-emerald-600 text-[10px] font-bold uppercase tracking-[0.18em]">Hoy</text>
                                    @endif
                                @endforeach
                            </svg>
                        </div>
                    @else
                        <div class="min-h-40 rounded-3xl border border-dashed border-slate-200 bg-slate-50/60"></div>
                    @endif
                </div>
            </article>

            <article class="rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-100/70">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Pulso financiero</h2>
                    <p class="mt-1 text-sm text-slate-500">Pendientes criticos del flujo de caja.</p>
                </div>
                <div class="grid gap-4 p-5">
                    <div class="rounded-3xl border border-amber-200 bg-amber-50/80 px-5 py-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-700">Provisiones pendientes</p>
                        <p class="mt-3 text-3xl font-semibold text-amber-900">S/ {{ number_format($pendingProvisions, 2) }}</p>
                    </div>
                    <div class="rounded-3xl border border-cyan-200 bg-cyan-50/80 px-5 py-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-700">Facturas pendientes</p>
                        <p class="mt-3 text-3xl font-semibold text-cyan-900">S/ {{ number_format($pendingInvoices, 2) }}</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-[1.25fr_1fr]">
            <article class="rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-100/70">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Embarques por estado</h2>
                    <p class="mt-1 text-sm text-slate-500">Foto actual del pipeline operativo.</p>
                </div>
                <div class="space-y-4 p-5">
                    @forelse ($shipmentsByStatus as $index => $row)
                        @php
                            $width = round(($row['total'] / $statusMax) * 100, 2);
                            $tones = ['bg-cyan-500', 'bg-emerald-500', 'bg-blue-500', 'bg-amber-500', 'bg-violet-500'];
                        @endphp
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $row['label'] }}</p>
                                <span class="text-sm font-semibold text-slate-500">{{ $row['total'] }}</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $tones[$index % count($tones)] }}" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center text-sm text-slate-500">
                            No hay embarques dentro del filtro actual.
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-100/70">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Provisiones vs facturacion</h2>
                </div>
                <div class="space-y-5 p-5">
                    @foreach ([
                        ['label' => 'Provisionado', 'value' => $financialTotals['provisions'], 'tone' => 'bg-amber-500'],
                        ['label' => 'Facturado', 'value' => $financialTotals['invoices'], 'tone' => 'bg-cyan-600'],
                    ] as $row)
                        @php
                            $width = round(($row['value'] / $financeMax) * 100, 2);
                        @endphp
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $row['label'] }}</p>
                                <span class="text-sm font-semibold text-slate-500">S/ {{ number_format($row['value'], 2) }}</span>
                            </div>
                            <div class="h-4 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $row['tone'] }}" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-100/70 xl:col-span-1">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Mix por tipo de carga</h2>
                </div>
                <div class="flex flex-col gap-5 p-5">
                    <div class="mx-auto w-[180px]">
                        <svg viewBox="0 0 120 120" class="w-full">
                            <circle cx="60" cy="60" r="42" fill="none" stroke="#e2e8f0" stroke-width="14"></circle>
                            @foreach ($shipmentsByCargoType as $index => $row)
                                @php
                                    $segment = ($row['total'] / $cargoTotal) * $donutCircumference;
                                    $dashArray = $segment.' '.($donutCircumference - $segment);
                                    $color = $donutColors[$index % count($donutColors)];
                                @endphp
                                <circle
                                    cx="60"
                                    cy="60"
                                    r="42"
                                    fill="none"
                                    stroke="{{ $color }}"
                                    stroke-width="14"
                                    stroke-linecap="round"
                                    stroke-dasharray="{{ $dashArray }}"
                                    stroke-dashoffset="{{ -$donutOffset }}"
                                    transform="rotate(-90 60 60)"
                                ></circle>
                                @php
                                    $donutOffset += $segment;
                                @endphp
                            @endforeach
                            <text x="60" y="56" text-anchor="middle" class="fill-slate-500 text-[10px] font-semibold uppercase tracking-[0.2em]">Total</text>
                            <text x="60" y="72" text-anchor="middle" class="fill-slate-900 text-xl font-semibold">{{ $cargoTotal }}</text>
                        </svg>
                    </div>

                    <div class="space-y-3">
                        @forelse ($shipmentsByCargoType as $index => $row)
                            @php
                                $color = $donutColors[$index % count($donutColors)];
                                $share = round(($row['total'] / $cargoTotal) * 100);
                            @endphp
                            <div class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="h-3 w-3 rounded-full" style="background-color: {{ $color }}"></span>
                                    <p class="font-semibold text-slate-900">{{ $row['label'] }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-slate-900">{{ $row['total'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $share }}%</p>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center text-sm text-slate-500">
                                No hay tipo de carga para graficar con el filtro actual.
                            </div>
                        @endforelse
                    </div>
                </div>
            </article>

            <article class="rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-100/70 xl:col-span-1">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Top clientes por embarques</h2>
                </div>
                <div class="space-y-4 p-5">
                    @forelse ($shipmentsByClient as $index => $client)
                        @php
                            $width = round(($client['total'] / $clientMax) * 100, 2);
                            $tones = ['bg-slate-800', 'bg-cyan-600', 'bg-emerald-600', 'bg-blue-600', 'bg-amber-500', 'bg-violet-500'];
                        @endphp
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $client['label'] }}</p>
                                <span class="text-sm font-semibold text-slate-500">{{ $client['total'] }}</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $tones[$index % count($tones)] }}" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center text-sm text-slate-500">
                            No hay clientes para comparar en este rango.
                        </div>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
</x-app-layout>
