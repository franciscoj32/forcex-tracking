{{-- Inicio: "¿que tengo que hacer hoy?" --}}
@use('App\Support\Money')
@php
    $levelDot = fn (string $level) => match ($level) {
        'danger' => 'bg-rose-600',
        'warning' => 'bg-amber-500',
        default => 'bg-slate-400',
    };
    $urgent = $inbox->whereIn('level', ['danger', 'warning'])->count();
    $now = \Carbon\CarbonImmutable::now();
    // Se muestran primero las mas urgentes; el resto con "Ver todas".
    $listLimit = 15;
    $showAllAlerts = request('alertas') === 'todas';
    $showAllTasks = request('tareas') === 'todas';
    $alertList = $showAllAlerts ? $alerts['activas'] : $alerts['activas']->take($listLimit);
    $taskList = $showAllTasks ? $inbox : $inbox->take($listLimit);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1>Hola, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
    </x-slot>

    <div class="space-y-4">
        @if (($board ?? null) !== null)
            @include('productivity.board._preview')
        @endif

        {{-- Pre-alertas de llegada pendientes: arriba de todo, hasta que se envien o se retengan.
             Cada aviso abre una ventana que carga el formulario de esa RO. --}}
        @if ($preAlerts->isNotEmpty())
            <section class="space-y-2" aria-label="Pre-alertas de llegada pendientes">
                @foreach ($preAlerts as $preAlert)
                    @include('alerts.pre-alert-trigger', ['alert' => $preAlert, 'opens' => 'remote', 'withShipment' => true])
                @endforeach
            </section>

            <div x-data="{
                    open: false, loading: false, failed: false, html: '', title: '',
                    async load(url, title) {
                        this.title = title; this.html = ''; this.failed = false; this.loading = true; this.open = true;
                        try {
                            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                            if (! response.ok) throw new Error(response.status);
                            this.html = await response.text();
                        } catch (e) {
                            this.failed = true;
                        } finally {
                            this.loading = false;
                        }
                    },
                 }"
                 x-on:open-pre-alert.window="load($event.detail.url, $event.detail.title)"
                 x-on:keydown.escape.window="open = false">
                <template x-teleport="body">
                    <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
                        <div class="fixed inset-0 bg-slate-900/40" x-on:click="open = false"></div>
                        <div class="relative flex items-end justify-center sm:items-center sm:p-4" style="min-height: 100%" x-on:click.self="open = false">
                            <section class="relative w-full max-w-5xl rounded-t-md bg-white shadow-lg sm:rounded-md" role="dialog" aria-modal="true" :aria-label="title">
                                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                                    <h3 class="fx-section-title" x-text="title"></h3>
                                    <button type="button" x-on:click="open = false" class="rounded p-1 text-slate-400 hover:text-slate-700" aria-label="Cerrar">
                                        <x-nav-icon name="close" class="h-5 w-5" />
                                    </button>
                                </div>
                                <p x-show="loading" class="px-5 py-8 text-center text-slate-500">Cargando…</p>
                                <p x-show="failed" class="px-5 py-8 text-center text-rose-700">No se pudo cargar la pre-alerta. Vuelve a intentarlo.</p>
                                <div x-html="html"></div>
                            </section>
                        </div>
                    </div>
                </template>
            </div>
        @endif

        {{-- Numeros del mes (Admin y Comercial) --}}
        @if ($month)
            @php
                $counts = $month['counts'];
                $totals = $month['totals'];
                $isAdmin = $month['type'] === 'admin';
            @endphp
            <section class="fx-card">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <h2 class="fx-section-title">{{ $isAdmin ? 'Empresa' : 'Mis ventas' }} · {{ $month['period']['label'] }}</h2>
                    <form method="GET" action="{{ route('dashboard') }}">
                        <label for="mes" class="sr-only">Mes</label>
                        <select class="fx-select mt-0 h-9 w-auto" id="mes" name="mes" onchange="this.form.submit()">
                            @foreach ($month['months'] as $key => $label)
                                <option value="{{ $key }}" @selected($month['period']['key'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="grid grid-cols-2 gap-4 p-4 tabular-nums lg:grid-cols-4">
                    <div>
                        <p class="text-xs text-slate-500">{{ $isAdmin ? 'Embarques' : 'Mis RO' }}</p>
                        <p class="mt-0.5 text-2xl font-semibold text-slate-900">{{ $counts['total'] }}</p>
                        @include('partials.cargo-mix', ['counts' => $counts])
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Venta</p>
                        <p class="mt-0.5 text-2xl font-semibold text-slate-900">{{ Money::format($totals['sales']) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">{{ $isAdmin ? 'Margen' : 'Mi margen' }}</p>
                        <p @class(['mt-0.5 text-2xl font-semibold', 'text-rose-700' => $totals['margin'] < 0, 'text-slate-900' => $totals['margin'] >= 0])>{{ Money::format($totals['margin']) }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $totals['margin_percent'] !== null ? $totals['margin_percent'].'% de la venta' : 'Sin venta' }}
                            @if ($totals['incomplete'] > 0)
                                · <span class="text-amber-700">{{ $totals['incomplete'] }} RO sin tipo de cambio</span>
                            @endif
                        </p>
                    </div>
                    @if ($isAdmin)
                        <a href="{{ route('embarques.index', ['vista' => 'retrasados']) }}" class="group">
                            <p class="text-xs text-slate-500">Retrasados hoy</p>
                            <p @class(['mt-0.5 text-2xl font-semibold group-hover:underline', 'text-rose-700' => $month['delayed'] > 0, 'text-slate-900' => $month['delayed'] === 0])>{{ $month['delayed'] }}</p>
                            <p class="text-xs text-slate-500">ETA vencida sin arribo</p>
                        </a>
                    @else
                        <div>
                            <p class="text-xs text-slate-500">Mi puesto en el ranking</p>
                            <p class="mt-0.5 text-2xl font-semibold text-slate-900">{{ $month['position'] ? $month['position'].'°' : '—' }}</p>
                            <p class="text-xs text-slate-500">{{ $month['position'] ? 'de '.$month['rankingSize'].' por margen' : 'Sin ventas en el mes' }}</p>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- Alertas criticas: plazos detectados por el sistema (se cierran solas) --}}
        @php
            $activeAlerts = $alerts['activas'];
            $hiddenAlerts = $alerts['vistas']->count() + $alerts['pospuestas']->count();
            $alertCounts = $activeAlerts->countBy('level');
            $user = auth()->user();
            $canActOnAlerts = $user->hasRole([\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_CUSTOMER, \App\Models\User::ROLE_OPERACIONES]);
            // Ops y Customer siempre ven el bloque; Admin y Comercial solo si hay algo.
            $showAlerts = $user->hasRole([\App\Models\User::ROLE_CUSTOMER, \App\Models\User::ROLE_OPERACIONES]) || $activeAlerts->isNotEmpty() || $hiddenAlerts > 0;
        @endphp
        @if ($showAlerts)
            <section class="fx-card scroll-mt-20" id="alertas">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div class="flex items-start gap-2">
                        <x-nav-icon name="bell" class="mt-0.5 h-5 w-5 text-slate-700" />
                        <div>
                            <h2 class="fx-section-title">Alertas</h2>
                            <p class="text-xs text-slate-500">
                                @if ($user->isAdmin())
                                    Alertas críticas que llevan más de {{ $alertLimits->escalationHours }} h sin atender.
                                @else
                                    Plazos que vencen en los próximos {{ $alertLimits->preventiveDays }} días. Se cierran solas cuando se registra el dato.
                                @endif
                            </p>
                        </div>
                    </div>
                    @if ($activeAlerts->isNotEmpty())
                        <div class="flex flex-wrap gap-2 text-xs font-semibold">
                            @foreach (['danger' => ['crítica', 'críticas'], 'warning' => ['advertencia', 'advertencias'], 'info' => ['preventiva', 'preventivas']] as $level => [$one, $many])
                                @if (($alertCounts[$level] ?? 0) > 0)
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 {{ \App\Services\Alerts\AlertLevel::style($level, 'soft') }} {{ \App\Services\Alerts\AlertLevel::style($level, 'text') }}">
                                        <span class="h-2 w-2 rounded-full {{ \App\Services\Alerts\AlertLevel::style($level, 'bar') }}"></span>
                                        {{ $alertCounts[$level] }} {{ $alertCounts[$level] === 1 ? $one : $many }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($activeAlerts->isEmpty())
                    <p class="px-4 py-4 text-slate-500">Sin plazos por vencer en los próximos {{ $alertLimits->preventiveDays }} días.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($alertList as $alert)
                            @include('alerts.row', ['alert' => $alert, 'actions' => $canActOnAlerts])
                        @endforeach
                    </ul>
                    @if ($activeAlerts->count() > $listLimit)
                        <a href="{{ route('dashboard', array_filter(['alertas' => $showAllAlerts ? null : 'todas', 'tareas' => request('tareas'), 'mes' => request('mes')])) }}#alertas"
                           class="block border-t border-slate-200 px-4 py-2.5 text-sm font-medium text-brand-700 hover:bg-slate-50">
                            {{ $showAllAlerts ? 'Ver solo las más urgentes' : 'Ver las '.$activeAlerts->count().' alertas' }}
                        </a>
                    @endif
                @endif

                @if ($hiddenAlerts > 0)
                    <div class="border-t border-slate-200">
                        @foreach (['vistas' => 'Vistas', 'pospuestas' => 'Pospuestas'] as $group => $groupLabel)
                            @if ($alerts[$group]->isNotEmpty())
                                <details class="group border-b border-slate-100 last:border-b-0">
                                    <summary class="flex cursor-pointer list-none items-center gap-1 px-4 py-2.5 text-sm font-medium text-brand-700 hover:bg-slate-50">
                                        <span class="transition-transform group-open:rotate-90">›</span>
                                        {{ $groupLabel }} ({{ $alerts[$group]->count() }})
                                    </summary>
                                    <ul class="divide-y divide-slate-100 bg-slate-50/60">
                                        @foreach ($alerts[$group] as $alert)
                                            @include('alerts.row', ['alert' => $alert, 'actions' => $canActOnAlerts])
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        {{-- Bandeja de tareas --}}
        <section class="fx-card">
            <div class="flex items-start gap-2 border-b border-slate-200 px-4 py-3">
                <x-nav-icon name="tasks" class="mt-0.5 h-5 w-5 text-slate-700" />
                <div>
                    <h2 class="fx-section-title">
                        Requiere tu acción
                        <span @class(['ml-1 font-normal', 'text-rose-700' => $urgent > 0, 'text-slate-500' => $urgent === 0])>({{ $inbox->count() }})</span>
                    </h2>
                    <p class="text-xs text-slate-500">Tareas que dependen de una persona. Desaparecen cuando se completan.</p>
                </div>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($taskList as $item)
                    <li class="grid gap-1 px-4 py-3 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_auto] sm:items-center sm:gap-4">
                        <div class="flex min-w-0 items-start gap-2">
                            <span class="fx-status-dot mt-1.5 {{ $levelDot($item['level']) }}"></span>
                            <div class="min-w-0">
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" class="block truncate font-medium text-slate-900 hover:underline">{{ $item['reference'] }}</a>
                                @else
                                    <p class="truncate font-medium text-slate-900">{{ $item['reference'] }}</p>
                                @endif
                                @if ($item['subject'])
                                    <p class="truncate text-xs text-slate-500">{{ $item['subject'] }}</p>
                                @endif
                            </div>
                        </div>
                        <p class="pl-[18px] text-slate-700 sm:pl-0">{{ $item['text'] }}</p>
                        @if ($item['action_url'])
                            <a href="{{ $item['action_url'] }}" class="fx-btn-link-primary pl-[18px] sm:pl-0 sm:text-right">{{ $item['action_label'] }}</a>
                        @endif
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-slate-500">Sin pendientes. Todo al día.</li>
                @endforelse
            </ul>
            @if ($inbox->count() > $listLimit)
                <a href="{{ route('dashboard', array_filter(['tareas' => $showAllTasks ? null : 'todas', 'alertas' => request('alertas'), 'mes' => request('mes')])) }}"
                   class="block border-t border-slate-200 px-4 py-2.5 text-sm font-medium text-brand-700 hover:bg-slate-50">
                    {{ $showAllTasks ? 'Ver solo las más urgentes' : 'Ver las '.$inbox->count().' tareas' }}
                </a>
            @endif
        </section>

        {{-- Admin: ranking y tendencia --}}
        @if ($month && $month['type'] === 'admin')
            @php $trendMax = max((float) $month['trend']->max('margin'), 1); @endphp
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                <section class="fx-card">
                    <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Ranking de comerciales · {{ $month['period']['label'] }}</h2>
                    @if ($month['ranking']->isEmpty())
                        <p class="px-4 py-4 text-slate-500">Sin embarques en el mes.</p>
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
                                    @php $rankingMax = max((float) $month['ranking']->max('margin'), 1); @endphp
                                    @foreach ($month['ranking'] as $row)
                                        <tr>
                                            <td class="fx-td text-slate-500">{{ $row['position'] }}</td>
                                            <td class="fx-td text-slate-900">{{ $row['name'] }}</td>
                                            <td class="fx-td text-right">{{ $row['shipments'] }}</td>
                                            <td class="fx-td whitespace-nowrap text-right">{{ Money::format($row['sales']) }}</td>
                                            <td @class(['fx-td whitespace-nowrap text-right font-medium', 'text-rose-700' => $row['margin'] < 0, 'text-slate-900' => $row['margin'] >= 0])>{{ Money::format($row['margin']) }}</td>
                                            <td class="fx-td text-right text-slate-500">{{ $row['margin_percent'] !== null ? $row['margin_percent'].'%' : '—' }}</td>
                                            <td class="fx-td w-32">
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

                <section class="fx-card">
                    <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Margen de los últimos 6 meses</h2>
                    <div class="flex h-52 items-end gap-2 px-4 pb-4 pt-6">
                        @foreach ($month['trend'] as $point)
                            @php $height = $point['margin'] > 0 ? max(3, round(($point['margin'] / $trendMax) * 100)) : 1; @endphp
                            <div class="flex h-full flex-1 flex-col items-center justify-end gap-1.5">
                                <span class="text-xs tabular-nums text-slate-600">{{ $point['margin'] > 0 ? number_format($point['margin'] / 1000, 1).'k' : '0' }}</span>
                                <div @class([
                                    'w-full max-w-12 rounded-t',
                                    'bg-accent-500' => $point['key'] === $month['period']['key'] && $point['margin'] >= 0,
                                    'bg-brand-400' => $point['key'] !== $month['period']['key'] && $point['margin'] >= 0,
                                    'bg-rose-500' => $point['margin'] < 0,
                                ]) style="height: {{ $height }}%"></div>
                                <span class="text-xs text-slate-500">{{ $point['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        @endif
    </div>
</x-app-layout>
