<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $client->razon_social }}</h1>
        <p class="text-sm text-slate-500">Detalle de cuenta, comportamiento de carga y seguimiento reciente.</p>
    </x-slot>

    <div class="grid gap-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="fx-card fx-card-body">
                <p class="text-sm text-slate-500">Embarques activos</p>
                <p class="mt-2 text-3xl font-semibold text-sky-700">{{ $client->shipments_active_count }}</p>
            </div>
            <div class="fx-card fx-card-body">
                <p class="text-sm text-slate-500">Embarques finalizados</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-700">{{ $client->shipments_finished_count }}</p>
            </div>
            <div class="fx-card fx-card-body">
                <p class="text-sm text-slate-500">En transito</p>
                <p class="mt-2 text-3xl font-semibold text-cyan-700">{{ $client->shipments_in_transit_count }}</p>
            </div>
            <div class="fx-card fx-card-body">
                <p class="text-sm text-slate-500">Pendientes de endoso</p>
                <p class="mt-2 text-3xl font-semibold text-amber-700">{{ $client->shipments_pending_endorsement_count }}</p>
            </div>
            <div class="fx-card fx-card-body">
                <p class="text-sm text-slate-500">Total historico</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $client->shipments_count }}</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.95fr_1.25fr]">
            <div class="fx-card fx-card-body">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Datos generales</h2>
                        <p class="mt-1 text-sm text-slate-500">Informacion comercial base del cliente.</p>
                    </div>
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('clientes.edit', $client) }}" class="fx-btn-secondary">Editar cuenta</a>
                    @endif
                </div>

                <dl class="mt-6 grid gap-4 md:grid-cols-2">
                    <div><dt class="text-sm text-slate-500">RUC</dt><dd class="font-medium">{{ $client->ruc }}</dd></div>
                    <div><dt class="text-sm text-slate-500">Correo</dt><dd class="font-medium">{{ $client->correo ?: 'No registrado' }}</dd></div>
                    <div><dt class="text-sm text-slate-500">Telefono</dt><dd class="font-medium">{{ $client->telefono ?: 'No registrado' }}</dd></div>
                    <div>
                        <dt class="text-sm text-slate-500">Estado</dt>
                        <dd class="font-medium">
                            <span class="fx-badge {{ $client->estado === 'activo' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                {{ ucfirst($client->estado) }}
                            </span>
                        </dd>
                    </div>
                    <div class="md:col-span-2"><dt class="text-sm text-slate-500">Direccion</dt><dd class="font-medium">{{ $client->direccion ?: 'No registrada' }}</dd></div>
                </dl>
            </div>

            <div class="fx-card">
                <div class="fx-card-header">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Lectura operativa</h2>
                        <p class="mt-1 text-sm text-slate-500">Resumen rapido para responder al cliente y priorizar gestion.</p>
                    </div>
                </div>
                <div class="grid gap-4 p-6 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-900">Cuenta con carga en curso</p>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ $client->shipments_active_count > 0 ? 'Si, actualmente mantiene operaciones activas y conviene revisar ETA, aduanas y endosos pendientes.' : 'No, por ahora no tiene embarques activos en seguimiento.' }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-900">Pendientes de endoso</p>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ $client->shipments_pending_endorsement_count > 0 ? 'Tiene embarques que todavia requieren control documentario o cierre de endoso.' : 'No registra pendientes de endoso en este momento.' }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-900">Historial cerrado</p>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ $client->shipments_finished_count > 0 ? 'Ya cuenta con operaciones finalizadas que pueden exportarse como reporte historico.' : 'Aun no tiene embarques marcados como finalizados.' }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-900">Accion sugerida</p>
                        <p class="mt-2 text-sm text-slate-600">
                            Filtra este cliente en Embarques para exportar su reporte por estado, fecha o seguimiento cuando lo solicite.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="fx-card">
            <div class="fx-card-header">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Embarques recientes</h2>
                    <p class="mt-1 text-sm text-slate-500">Detalle operativo de los ultimos movimientos registrados para esta cuenta.</p>
                </div>
                <a href="{{ route('embarques.index', ['client_id' => $client->id]) }}" class="fx-btn-secondary">Ver todos en embarques</a>
            </div>
            <div class="overflow-x-auto">
                <table class="fx-table">
                    <thead>
                        <tr>
                            <th class="fx-th">OT</th>
                            <th class="fx-th">BL</th>
                            <th class="fx-th">Tipo</th>
                            <th class="fx-th">Ruta</th>
                            <th class="fx-th">ETA</th>
                            <th class="fx-th">Estado actual</th>
                            <th class="fx-th">Endoso</th>
                            <th class="fx-th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($client->shipments as $shipment)
                            <tr>
                                <td class="fx-td font-semibold">{{ $shipment->nro_orden_trabajo }}</td>
                                <td class="fx-td">{{ $shipment->bl }}</td>
                                <td class="fx-td">{{ $shipment->tipo_carga }}</td>
                                <td class="fx-td">{{ $shipment->origen }} -> {{ $shipment->destino_final }}</td>
                                <td class="fx-td">{{ optional($shipment->fecha_eta)->format('d/m/Y') ?: '-' }}</td>
                                <td class="fx-td">{{ $shipment->estado_actual }}</td>
                                <td class="fx-td">
                                    <span class="fx-badge {{ $shipment->estado_endoso === 'completado' ? 'bg-emerald-100 text-emerald-700' : ($shipment->estado_endoso === 'en_proceso' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
                                        {{ ucfirst(str_replace('_', ' ', $shipment->estado_endoso)) }}
                                    </span>
                                </td>
                                <td class="fx-td"><a href="{{ route('embarques.show', $shipment) }}" class="text-cyan-700">Ver embarque</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="fx-td text-center text-slate-500">Este cliente aun no tiene embarques registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
