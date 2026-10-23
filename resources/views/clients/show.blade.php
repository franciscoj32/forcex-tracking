@php
    $row = 'flex items-baseline justify-between gap-4 py-1.5';
    $dt = 'shrink-0 text-slate-500';
    $dd = 'min-w-0 text-right text-slate-900 break-words';
@endphp

<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block"><a href="{{ route('clientes.index') }}" class="hover:underline">Clientes</a> / RUC {{ $client->ruc }}</p>
        <h1>{{ $client->razon_social }}</h1>
    </x-slot>

    <x-slot name="actions">
        <a href="{{ route('embarques.index', ['client_id' => $client->id, 'vista' => 'todos']) }}" class="fx-btn-secondary hidden sm:inline-flex">Ver embarques</a>
        @can('create', \App\Models\Quotation::class)
            <a href="{{ route('cotizaciones.create', ['client_id' => $client->id]) }}" class="fx-btn-secondary">Cotizar</a>
        @endcan
        @can('update', $client)
            <a href="{{ route('clientes.edit', $client) }}" class="fx-btn-secondary">Editar</a>
        @endcan
    </x-slot>

    <div class="space-y-4">
        {{-- Aprobacion pendiente o rechazada --}}
        @unless ($client->isApproved())
            <section @class(['fx-card border-l-4 p-4', 'border-l-rose-600' => $client->isRejected(), 'border-l-amber-500' => ! $client->isRejected()])>
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,28rem)]">
                    <div>
                        <h2 class="fx-section-title">{{ $client->isRejected() ? 'Cliente rechazado' : 'Pendiente de aprobación' }}</h2>
                        <p class="mt-1 text-slate-700">
                            @if ($client->isRejected())
                                Motivo: {{ $client->observacion_aprobacion }}. Al corregir la ficha vuelve a revisión.
                            @else
                                Registrado por {{ $client->registeredBy->name }} el {{ $client->created_at->format('d/m/Y') }}. No se le pueden emitir RO hasta aprobarlo.
                            @endif
                        </p>
                    </div>

                    @can('approve', $client)
                        <div class="space-y-3">
                            <form method="POST" action="{{ route('clientes.approve', $client) }}" class="flex gap-2">
                                @csrf
                                <label for="approve_note" class="sr-only">Nota de aprobación</label>
                                <input class="fx-input mt-0" id="approve_note" name="observacion_aprobacion" placeholder="Nota (opcional)">
                                <button type="submit" class="fx-btn-primary">Aprobar</button>
                            </form>
                            @unless ($client->isRejected())
                                <form method="POST" action="{{ route('clientes.reject', $client) }}" class="flex gap-2">
                                    @csrf
                                    <label for="reject_note" class="sr-only">Motivo del rechazo</label>
                                    <input class="fx-input mt-0" id="reject_note" name="observacion_aprobacion" placeholder="Motivo del rechazo" required>
                                    <button type="submit" class="fx-btn-secondary text-rose-700">Rechazar</button>
                                </form>
                            @endunless
                        </div>
                    @endcan
                </div>
            </section>
        @endunless

        {{-- Numeros --}}
        <section class="fx-card grid grid-cols-3 gap-4 p-4 tabular-nums">
            <a href="{{ route('embarques.index', ['client_id' => $client->id]) }}" class="group">
                <p class="text-xs text-slate-500">Embarques activos</p>
                <p class="mt-0.5 text-2xl font-semibold text-slate-900 group-hover:underline">{{ $client->shipments_active_count }}</p>
            </a>
            <a href="{{ route('embarques.index', ['client_id' => $client->id, 'vista' => 'retrasados']) }}" class="group">
                <p class="text-xs text-slate-500">Retrasados</p>
                <p @class(['mt-0.5 text-2xl font-semibold group-hover:underline', 'text-rose-700' => $client->shipments_delayed_count > 0, 'text-slate-900' => $client->shipments_delayed_count === 0])>{{ $client->shipments_delayed_count }}</p>
            </a>
            <div>
                <p class="text-xs text-slate-500">Registrados en {{ now()->year }}</p>
                <p class="mt-0.5 text-2xl font-semibold text-slate-900">{{ $client->shipments_year_count }}</p>
                <p class="text-xs text-slate-500">{{ $client->shipments_count }} en total</p>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
            <section class="fx-card">
                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Datos de la cuenta</h2>
                <dl class="divide-y divide-slate-100 px-4 py-1">
                    <div class="{{ $row }}"><dt class="{{ $dt }}">RUC</dt><dd class="{{ $dd }} tabular-nums">{{ $client->ruc }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">Crédito</dt><dd class="{{ $dd }}">{{ $client->creditLabel() }}</dd></div>
                    <div class="{{ $row }}">
                        <dt class="{{ $dt }}">Línea de crédito</dt>
                        <dd class="{{ $dd }} tabular-nums">{{ $client->linea_credito ? $client->moneda_linea_credito.' '.number_format($client->linea_credito, 2) : 'No aplica' }}</dd>
                    </div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">Dirección fiscal</dt><dd class="{{ $dd }}">{{ $client->direccion ?: '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">Correo</dt><dd class="{{ $dd }}">{{ $client->correo ?: '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="{{ $dt }}">Teléfono</dt><dd class="{{ $dd }}">{{ $client->telefono ?: '—' }}</dd></div>
                    <div class="{{ $row }}">
                        <dt class="{{ $dt }}">Aprobación</dt>
                        <dd class="{{ $dd }}">
                            {{ $client->approvalLabel() }}
                            @if ($client->isApproved() && $client->approvedBy)
                                <span class="block text-xs text-slate-500">{{ $client->approvedBy->name }} · {{ $client->aprobado_en?->format('d/m/Y') }}</span>
                            @endif
                        </dd>
                    </div>
                    @if ($client->estado !== 'activo')
                        <div class="{{ $row }}"><dt class="{{ $dt }}">Estado</dt><dd class="{{ $dd }}">Inactivo</dd></div>
                    @endif
                </dl>
            </section>

            @include('clients._contacts')
        </div>

        <section class="fx-card">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Embarques recientes</h2>
            @if ($client->shipments->isEmpty())
                <p class="px-4 py-4 text-slate-500">Sin embarques.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="fx-table">
                        <thead>
                            <tr>
                                <th class="fx-th">OT / RO</th>
                                <th class="fx-th">Tipo</th>
                                <th class="fx-th">Ruta</th>
                                <th class="fx-th">ETA</th>
                                <th class="fx-th">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($client->shipments as $shipment)
                                <tr>
                                    <td class="fx-td">
                                        <a href="{{ route('embarques.show', $shipment) }}" class="font-semibold text-slate-900 hover:underline">{{ $shipment->nro_orden_trabajo }}</a>
                                        <p class="text-xs text-slate-500">{{ $shipment->routing_order }}</p>
                                    </td>
                                    <td class="fx-td">{{ $shipment->tipo_carga === 'AEREO' ? 'Aéreo' : $shipment->tipo_carga }}</td>
                                    <td class="fx-td">{{ $shipment->puerto_carga }} → {{ $shipment->puerto_descarga }}</td>
                                    <td class="fx-td whitespace-nowrap">{{ $shipment->fecha_eta?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="fx-td">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="fx-status-dot {{ \App\Support\ChartPalette::stage($shipment->estado_actual)['dot'] }}"></span>
                                            {{ $shipment->estado_actual }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
