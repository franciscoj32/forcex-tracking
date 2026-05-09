<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Clientes</h1>
        <p class="text-sm text-slate-500">Base comercial y operativa de cuentas con sus embarques relacionados.</p>
    </x-slot>

    <div class="fx-card">
        <div class="fx-card-header">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Listado de clientes</h2>
                <p class="mt-1 text-sm text-slate-500">Consulta actividad, volumen y estado general de cada cuenta.</p>
            </div>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('clientes.create') }}" class="fx-btn-primary">Nuevo cliente</a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="fx-table">
                <thead>
                    <tr>
                        <th class="fx-th">Razon social</th>
                        <th class="fx-th">RUC</th>
                        <th class="fx-th">Activos</th>
                        <th class="fx-th">Finalizados</th>
                        <th class="fx-th">Total</th>
                        <th class="fx-th">Estado</th>
                        <th class="fx-th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($clients as $client)
                        <tr>
                            <td class="fx-td font-semibold">{{ $client->razon_social }}</td>
                            <td class="fx-td">{{ $client->ruc }}</td>
                            <td class="fx-td">
                                <span class="fx-badge bg-sky-100 text-sky-800">{{ $client->shipments_active_count }}</span>
                            </td>
                            <td class="fx-td">
                                <span class="fx-badge bg-emerald-100 text-emerald-800">{{ $client->shipments_finished_count }}</span>
                            </td>
                            <td class="fx-td font-medium">{{ $client->shipments_count }}</td>
                            <td class="fx-td">
                                <span class="fx-badge {{ $client->estado === 'activo' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                    {{ ucfirst($client->estado) }}
                                </span>
                            </td>
                            <td class="fx-td"><a href="{{ route('clientes.show', $client) }}" class="text-cyan-700">Ver cuenta</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="fx-td text-center text-slate-500">No hay clientes registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">{{ $clients->links() }}</div>
    </div>
</x-app-layout>
