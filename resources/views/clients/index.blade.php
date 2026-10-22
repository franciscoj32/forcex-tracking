@php
    $views = [
        '' => ['label' => 'Todos', 'icon' => 'list', 'count' => array_sum($approvalCounts)],
        'pendiente' => ['label' => 'Por aprobar', 'icon' => 'clock', 'count' => $approvalCounts['pendiente'] ?? 0],
        'aprobado' => ['label' => 'Aprobados', 'icon' => 'check-circle', 'count' => $approvalCounts['aprobado'] ?? 0],
        'rechazado' => ['label' => 'Rechazados', 'icon' => 'x-circle', 'count' => $approvalCounts['rechazado'] ?? 0],
    ];
    $grid = 'md:grid md:grid-cols-[minmax(0,1.6fr)_8rem_minmax(0,1fr)_7rem_8rem] md:items-center md:gap-4';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1>Clientes</h1>
    </x-slot>

    <x-slot name="actions">
        @can('create', \App\Models\Client::class)
            <a href="{{ route('clientes.create') }}" class="fx-btn-primary">Nuevo cliente</a>
        @endcan
    </x-slot>

    <nav class="-mx-4 overflow-x-auto border-b border-slate-200 px-4 sm:mx-0 sm:px-0" aria-label="Vistas">
        <div class="fx-tabs">
            @foreach ($views as $value => $view)
                @php $isCurrent = $selectedApproval === $value; @endphp
                <a href="{{ route('clientes.index', array_filter(['aprobacion' => $value])) }}" @class(['fx-tab', 'fx-tab-active' => $isCurrent])>
                    <x-nav-icon :name="$view['icon']" class="fx-tab-icon" />
                    {{ $view['label'] }}
                    <span @class([
                        'tabular-nums',
                        'font-semibold text-amber-700' => $value === 'pendiente' && $view['count'] > 0,
                        'text-slate-400' => ! ($value === 'pendiente' && $view['count'] > 0) && ! $isCurrent,
                    ])>{{ $view['count'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    <div class="mt-4 overflow-hidden rounded-md border border-slate-200 bg-white">
        <div class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-medium text-slate-500 {{ $grid }}">
            <span>Razón social</span>
            <span>RUC</span>
            <span>Crédito</span>
            <span class="text-right">Activos · total</span>
            <span>Estado</span>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse ($clients as $client)
                @php $url = route('clientes.show', $client); @endphp
                <li x-data x-on:click="if (! $event.target.closest('a')) window.location = @js($url)" class="cursor-pointer px-4 py-3 hover:bg-slate-50 {{ $grid }}">
                    <div class="min-w-0">
                        <a href="{{ $url }}" class="block truncate font-semibold text-slate-900 hover:underline">{{ $client->razon_social }}</a>
                        <p class="truncate text-xs text-slate-500">Registrado por {{ $client->registeredBy->name }}</p>
                    </div>
                    <p class="tabular-nums text-slate-700"><span class="text-xs text-slate-500 md:hidden">RUC </span>{{ $client->ruc }}</p>
                    <p class="text-slate-700">{{ $client->creditLabel() }}</p>
                    <p class="tabular-nums text-slate-700 md:text-right">
                        <span class="font-semibold text-slate-900">{{ $client->shipments_active_count }}</span> · {{ $client->shipments_count }}
                        <span class="text-xs text-slate-500 md:hidden">embarques activos · total</span>
                    </p>
                    <div class="mt-1 md:mt-0">
                        @if ($client->isPendingApproval())
                            <span class="fx-badge bg-amber-100 text-amber-800">Por aprobar</span>
                        @elseif ($client->isRejected())
                            <span class="fx-badge bg-rose-100 text-rose-700">Rechazado</span>
                        @elseif ($client->estado !== 'activo')
                            <span class="fx-badge bg-slate-100 text-slate-600">Inactivo</span>
                        @else
                            <span class="text-slate-500">Aprobado</span>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-slate-500">Sin clientes en esta vista.</li>
            @endforelse
        </ul>
    </div>

    @if ($clients->hasPages())
        <div class="mt-4">{{ $clients->links() }}</div>
    @endif
</x-app-layout>
