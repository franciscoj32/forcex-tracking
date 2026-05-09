<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Documentos</h1>
                <p class="text-sm text-slate-500">Vista centralizada de cargas con archivos disponibles.</p>
    </x-slot>

    <div class="fx-card">
        <div class="fx-card-header">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Embarques con documentos</h2>
                <p class="text-sm text-slate-500">Consulta rápida por embarque y cantidad de archivos.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="fx-table">
                <thead>
                    <tr>
                        <th class="fx-th">OT</th>
                        <th class="fx-th">Cliente</th>
                        <th class="fx-th">Estado</th>
                        <th class="fx-th">Archivos</th>
                        <th class="fx-th">Último documento</th>
                        <th class="fx-th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($shipments as $shipment)
                        <tr>
                            <td class="fx-td font-semibold">{{ $shipment->nro_orden_trabajo }}</td>
                            <td class="fx-td">{{ $shipment->client->razon_social }}</td>
                            <td class="fx-td">{{ $shipment->estado_actual }}</td>
                            <td class="fx-td">{{ $shipment->documents->count() }}</td>
                            <td class="fx-td">{{ $shipment->documents->first()?->tipo_documento ?: '-' }}</td>
                            <td class="fx-td">
                                <a href="{{ route('embarques.documentos.index', $shipment) }}" class="text-cyan-700 hover:text-cyan-600">Ver documentos</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="fx-td text-center text-slate-500">No hay documentos cargados todavía.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">{{ $shipments->links() }}</div>
    </div>
</x-app-layout>
