@php
    $grid = 'md:grid md:grid-cols-[9rem_minmax(0,1.5fr)_minmax(0,1.2fr)_minmax(0,1fr)_9rem_7rem] md:items-center md:gap-4';
@endphp

<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block">Clientes</p>
        <h1>Cotizaciones</h1>
    </x-slot>

    <x-slot name="actions">
        @can('create', \App\Models\Quotation::class)
            <a href="{{ route('cotizaciones.create') }}" class="fx-btn-primary">Nueva cotización</a>
        @endcan
    </x-slot>

    <x-view-tabs :views="$views" :current="$currentView" />

    <form method="GET" action="{{ route('cotizaciones.index') }}" class="mt-4 flex flex-wrap items-end gap-2" role="search">
        @if ($currentView !== '')
            <input type="hidden" name="vista" value="{{ $currentView }}">
        @endif
        <div class="min-w-[16rem] flex-1 sm:max-w-sm">
            <label class="sr-only" for="q">Buscar</label>
            <input class="fx-input mt-0" id="q" name="q" type="search" value="{{ $search }}" placeholder="Buscar por número, RUC o razón social">
        </div>
        @if ($comerciales->isNotEmpty())
            <div>
                <label class="sr-only" for="comercial_id">Comercial</label>
                <select class="fx-select mt-0" id="comercial_id" name="comercial_id" onchange="this.form.submit()">
                    <option value="">Todos los comerciales</option>
                    @foreach ($comerciales as $comercial)
                        <option value="{{ $comercial->id }}" @selected($comercialId === $comercial->id)>{{ $comercial->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button type="submit" class="fx-btn-secondary">Buscar</button>
        @if ($search !== '' || $comercialId)
            <a href="{{ route('cotizaciones.index', array_filter(['vista' => $currentView])) }}" class="fx-btn-link-primary">Limpiar</a>
        @endif
    </form>

    <div class="mt-4 overflow-hidden rounded-md border border-slate-200 bg-white">
        <div class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-medium text-slate-500 {{ $grid }}">
            <span>Número · fecha</span>
            <span>Cliente</span>
            <span>Servicio</span>
            <span>Comercial</span>
            <span class="text-right">Total</span>
            <span>Estado</span>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse ($quotations as $quotation)
                @php $url = route('cotizaciones.show', $quotation); @endphp
                <li x-data x-on:click="if (! $event.target.closest('a')) window.location = @js($url)" class="cursor-pointer px-4 py-3 hover:bg-slate-50 {{ $grid }}">
                    <div>
                        <a href="{{ $url }}" class="font-semibold tabular-nums text-slate-900 hover:underline">{{ $quotation->numero }}</a>
                        <p class="text-xs text-slate-500">{{ $quotation->fecha->format('d/m/Y') }}</p>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-slate-900">{{ $quotation->razon_social }}</p>
                        <p class="truncate text-xs text-slate-500">
                            RUC {{ $quotation->ruc }}{{ $quotation->client_id ? '' : ' · Prospecto' }}
                        </p>
                    </div>
                    <p class="min-w-0 truncate text-slate-700">
                        {{ $quotation->cargoLabel() }}{{ $quotation->routeLabel() !== '' ? ' · '.$quotation->routeLabel() : '' }}
                    </p>
                    <p class="truncate text-slate-700"><span class="text-xs text-slate-500 md:hidden">Comercial </span>{{ $quotation->comercial?->name ?? '—' }}</p>
                    <p class="tabular-nums text-slate-900 md:text-right">{{ \App\Support\Money::formatTotals($quotation->grandTotals()) }}</p>
                    <div class="mt-1 md:mt-0">
                        <span class="fx-badge {{ $quotation->statusBadge() }}">{{ $quotation->statusLabel() }}</span>
                    </div>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-slate-500">
                    Sin cotizaciones en esta vista.
                    @can('create', \App\Models\Quotation::class)
                        <a href="{{ route('cotizaciones.create') }}" class="fx-btn-link-primary">Crear una</a>
                    @endcan
                </li>
            @endforelse
        </ul>
    </div>

    @if ($quotations->hasPages())
        <div class="mt-4">{{ $quotations->links() }}</div>
    @endif
</x-app-layout>
