<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Bienvenido, {{ auth()->user()->name }}</h1>
                <p class="text-sm text-slate-500">Este es el estado de embarques al dia de hoy.</p>
            </div>

            @if (auth()->user()->hasRole([\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_OPERACIONES, \App\Models\User::ROLE_COMERCIAL]))
                <a href="{{ route('reportes.index') }}" class="fx-btn-primary justify-center">
                    Ver reportes
                </a>
            @endif
        </div>
    </x-slot>

    @if ($mode === 'interno')
        <div class="space-y-6">
            <section class="grid gap-6 xl:grid-cols-2">
                <div class="fx-card">
                    <div class="fx-card-header">
                        <h2 class="text-lg font-semibold text-slate-900">Alertas de cambio de estado</h2>
                    </div>
                    <div class="space-y-3 p-6">
                        @forelse ($statusAlerts as $shipment)
                            <a href="{{ route('embarques.show', $shipment) }}" class="flex items-start justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 transition hover:border-cyan-300 hover:bg-cyan-50/40">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $shipment->nro_orden_trabajo }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $shipment->client->razon_social }}</p>
                                    <p class="mt-2 text-sm text-slate-600">
                                        {{ $shipment->estado_actual }} · {{ optional($shipment->events->first()?->fecha)->format('d/m/Y H:i') ?: '-' }}
                                    </p>
                                </div>
                                <span class="fx-badge bg-blue-100 text-blue-700">Cambio</span>
                            </a>
                        @empty
                            <div class="min-h-36 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60"></div>
                        @endforelse
                    </div>
                </div>

                <div class="fx-card">
                    <div class="fx-card-header">
                        <h2 class="text-lg font-semibold text-slate-900">Alertas de llegada y ETA</h2>
                    </div>
                    <div class="space-y-3 p-6">
                        @forelse ($arrivalAlerts as $shipment)
                            @php
                                $latestEventDate = $shipment->events->first()?->fecha;
                                $isUpcomingEta = $shipment->fecha_eta
                                    && $shipment->fecha_eta->between(now()->startOfDay(), now()->copy()->addDays(3)->endOfDay())
                                    && ! in_array($shipment->estado_actual, ['Entregado', 'Finalizado'], true);
                            @endphp
                            <a href="{{ route('embarques.show', $shipment) }}" class="flex items-start justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 transition hover:border-cyan-300 hover:bg-cyan-50/40">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $shipment->nro_orden_trabajo }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $shipment->client->razon_social }}</p>
                                    <p class="mt-2 text-sm text-slate-600">
                                        {{ $isUpcomingEta ? 'ETA' : 'Arribo' }} {{ optional($shipment->fecha_eta)->format('d/m/Y') ?: optional($latestEventDate)->format('d/m/Y H:i') ?: '-' }}
                                    </p>
                                </div>
                                <span class="fx-badge {{ $isUpcomingEta ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $isUpcomingEta ? 'ETA' : 'Llegada' }}
                                </span>
                            </a>
                        @empty
                            <div class="min-h-36 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60"></div>
                        @endforelse
                    </div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
                <div class="fx-card">
                    <div class="fx-card-header">
                        <h2 class="text-lg font-semibold text-slate-900">Mis cargas vinculadas</h2>
                    </div>
                    <div class="space-y-3 p-6">
                        @forelse ($assignedActiveShipments as $shipment)
                            <a href="{{ route('embarques.show', $shipment) }}" class="block rounded-2xl border border-slate-200 p-4 transition hover:border-cyan-300 hover:bg-cyan-50/40">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-slate-900">{{ $shipment->nro_orden_trabajo }}</p>
                                            <span class="fx-badge {{ $shipment->pivot->assignment_type === 'responsable' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700' }}">
                                                {{ $shipment->pivot->assignment_type === 'responsable' ? 'Responsable' : 'Seguimiento' }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-500">{{ $shipment->client->razon_social }}</p>
                                        <p class="mt-2 text-sm text-slate-600">
                                            ETA {{ optional($shipment->fecha_eta)->format('d/m/Y') ?: '-' }}
                                        </p>
                                    </div>
                                    <span class="fx-badge bg-cyan-100 text-cyan-800">{{ $shipment->estado_actual }}</span>
                                </div>
                            </a>
                        @empty
                            <div class="min-h-36 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60"></div>
                        @endforelse
                    </div>
                </div>

                <div class="fx-card">
                    <div class="fx-card-header">
                        <h2 class="text-lg font-semibold text-slate-900">Ultimos movimientos</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="fx-table">
                            <thead>
                                <tr>
                                    <th class="fx-th">OT</th>
                                    <th class="fx-th">Cliente</th>
                                    <th class="fx-th">Estado</th>
                                    <th class="fx-th">Ultimo evento</th>
                                    <th class="fx-th"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($recentMovements as $shipment)
                                    <tr>
                                        <td class="fx-td font-semibold">{{ $shipment->nro_orden_trabajo }}</td>
                                        <td class="fx-td">{{ $shipment->client->razon_social }}</td>
                                        <td class="fx-td">{{ $shipment->estado_actual }}</td>
                                        <td class="fx-td">{{ optional($shipment->events->first()?->fecha)->format('d/m/Y H:i') ?? '-' }}</td>
                                        <td class="fx-td"><a href="{{ route('embarques.show', $shipment) }}" class="text-cyan-700 hover:text-cyan-600">Ver</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="fx-td text-center text-slate-500">-</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-3">
            <div class="fx-card fx-card-body">
                <p class="text-sm font-medium text-slate-500">Mis cargas activas</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $activeShipments->count() }}</p>
            </div>
            <div class="fx-card fx-card-body">
                <p class="text-sm font-medium text-slate-500">Proximas llegadas</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $upcomingArrivals->count() }}</p>
            </div>
            <div class="fx-card fx-card-body">
                <p class="text-sm font-medium text-slate-500">Documentos recientes</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $recentDocuments->count() }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <div class="fx-card">
                <div class="fx-card-header">
                    <h2 class="text-lg font-semibold text-slate-900">Mis cargas activas</h2>
                </div>
                <div class="space-y-4 p-6">
                    @forelse ($activeShipments as $shipment)
                        <a href="{{ route('embarques.show', $shipment) }}" class="block rounded-2xl border border-slate-200 p-4 hover:border-cyan-300 hover:bg-cyan-50/50">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $shipment->nro_orden_trabajo }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $shipment->puerto_carga }} -> {{ $shipment->destino_final }}</p>
                                </div>
                                <span class="fx-badge bg-cyan-100 text-cyan-800">{{ $shipment->estado_actual }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="min-h-36 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60"></div>
                    @endforelse
                </div>
            </div>

            <div class="space-y-6">
                <div class="fx-card">
                    <div class="fx-card-header">
                        <h2 class="text-lg font-semibold text-slate-900">Ultimo estado</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            @forelse ($latestStatus as $shipment)
                                <div class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $shipment->nro_orden_trabajo }}</p>
                                        <p class="text-sm text-slate-500">{{ $shipment->estado_actual }}</p>
                                    </div>
                                    <span class="text-sm text-slate-500">{{ optional($shipment->updated_at)->format('d/m/Y') }}</span>
                                </div>
                            @empty
                                <div class="min-h-24 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60"></div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="fx-card">
                    <div class="fx-card-header">
                        <h2 class="text-lg font-semibold text-slate-900">Ultimos documentos</h2>
                    </div>
                    <div class="space-y-3 p-6">
                        @forelse ($recentDocuments as $document)
                            <div class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $document->tipo_documento }}</p>
                                    <p class="text-sm text-slate-500">{{ $document->shipment->nro_orden_trabajo }}</p>
                                </div>
                                <a href="{{ route('documentos.download', $document) }}" class="text-sm font-semibold text-cyan-700">Descargar</a>
                            </div>
                        @empty
                            <div class="min-h-24 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60"></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
