<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Tracking</h1>
                <p class="text-sm text-slate-500">Vista operativa del estado y último movimiento de cada embarque.</p>
    </x-slot>

    <div class="fx-card">
        <div class="fx-card-header">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Seguimiento de embarques</h2>
                <p class="text-sm text-slate-500">Acceso directo a la línea de tiempo por carga.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="fx-table">
                <thead>
                    <tr>
                        <th class="fx-th">OT</th>
                        <th class="fx-th">Cliente</th>
                        <th class="fx-th">Estado actual</th>
                        <th class="fx-th">Último evento</th>
                        <th class="fx-th">ETA</th>
                        <th class="fx-th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($shipments as $shipment)
                        <tr>
                            <td class="fx-td font-semibold">{{ $shipment->nro_orden_trabajo }}</td>
                            <td class="fx-td">{{ $shipment->client->razon_social }}</td>
                            <td class="fx-td">{{ $shipment->estado_actual }}</td>
                            <td class="fx-td">{{ $shipment->events->first()?->estado ?: 'Sin eventos' }}</td>
                            <td class="fx-td">{{ optional($shipment->fecha_eta)->format('d/m/Y') ?: '-' }}</td>
                            <td class="fx-td">
                                <a href="{{ route('embarques.tracking.index', $shipment) }}" class="text-cyan-700 hover:text-cyan-600">Ver tracking</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="fx-td text-center text-slate-500">No hay embarques para tracking.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">{{ $shipments->links() }}</div>
    </div>
</x-app-layout>
