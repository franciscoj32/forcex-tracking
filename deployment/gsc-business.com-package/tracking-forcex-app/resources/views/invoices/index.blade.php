<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Facturacion</h1>
        <p class="text-sm text-slate-500">Consulta facturas agrupadas por embarque y despliega el detalle sin salir del listado.</p>
    </x-slot>

    <div class="fx-card overflow-hidden">
        <div class="fx-card-header items-start">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Listado de facturas</h2>
                <p class="mt-1 text-sm text-slate-500">Busca por numero de factura o numero de embarque.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if (auth()->user()->hasRole(['admin', 'operaciones']))
                    <a href="{{ route('facturacion.create') }}" class="inline-flex items-center justify-center rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-500">
                        Nueva factura
                    </a>
                @endif
            </div>
        </div>

        <div class="px-6 py-4">
            <div class="flex flex-col gap-4">
                <form method="GET" action="{{ route('facturacion.index') }}" class="grid gap-3">
                    <div class="flex items-center gap-3">
                        <input
                            type="search"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar numero de factura o embarque"
                            class="fx-input mt-0 min-w-0 flex-1 basis-0"
                        >
                        <button type="submit" class="inline-flex h-[46px] min-w-[132px] shrink-0 items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500">
                            Filtrar
                        </button>
                        @if ($search !== '')
                            <a href="{{ route('facturacion.index') }}" class="fx-btn-secondary shrink-0 justify-center">Limpiar</a>
                        @endif
                    </div>
                </form>

                <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 text-xs font-medium text-slate-500">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">Busqueda: {{ $search !== '' ? $search : 'Todas' }}</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="fx-table w-full text-[13px]">
                <thead>
                    <tr>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5 text-center"></th>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5">OT</th>
                        <th class="fx-th px-3 py-2.5">Cliente</th>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5">Facturas</th>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5">Total</th>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5">Ultima factura</th>
                        <th class="fx-th whitespace-nowrap px-3 py-2.5 text-right">Acciones</th>
                    </tr>
                </thead>
                @forelse ($shipments as $shipment)
                    @php
                        $lastInvoiceAt = $shipment->invoices_max_created_at
                            ? \Illuminate\Support\Carbon::parse($shipment->invoices_max_created_at)
                            : null;
                        $matchesShipment = $search !== '' && str_contains(
                            \Illuminate\Support\Str::lower($shipment->nro_orden_trabajo),
                            \Illuminate\Support\Str::lower($search)
                        );
                        $visibleInvoices = $search === '' || $matchesShipment
                            ? $shipment->invoices
                            : $shipment->invoices->filter(fn ($invoice) => str_contains(
                                \Illuminate\Support\Str::lower($invoice->numero),
                                \Illuminate\Support\Str::lower($search)
                            ));
                        $rowClass = $loop->odd ? 'bg-sky-50/60' : 'bg-emerald-50/50';
                    @endphp
                    <tbody x-data="{ open: {{ $search !== '' ? 'true' : 'false' }} }" class="divide-y divide-slate-100 bg-white">
                        <tr class="{{ $rowClass }}">
                            <td class="fx-td px-3 py-3 text-center">
                                <button
                                    type="button"
                                    x-on:click="open = !open"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-white/70 hover:text-cyan-600"
                                    :aria-expanded="open.toString()"
                                    aria-label="Mostrar u ocultar detalle"
                                >
                                    <svg class="h-4 w-4 transition-transform duration-200" :class="open ? 'rotate-180 text-cyan-600' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6"></path>
                                    </svg>
                                </button>
                            </td>
                            <td class="fx-td whitespace-nowrap px-3 py-3 font-semibold text-slate-900">{{ $shipment->nro_orden_trabajo }}</td>
                            <td class="fx-td px-3 py-3">{{ $shipment->client->razon_social }}</td>
                            <td class="fx-td whitespace-nowrap px-3 py-3">
                                <span class="fx-badge bg-cyan-100 text-cyan-800">{{ $shipment->invoices_count }} factura(s)</span>
                            </td>
                            <td class="fx-td whitespace-nowrap px-3 py-3 font-semibold text-slate-900">S/ {{ number_format((float) ($shipment->invoices_sum_monto ?? 0), 2) }}</td>
                            <td class="fx-td whitespace-nowrap px-3 py-3">{{ $lastInvoiceAt?->format('d/m/Y') ?: '-' }}</td>
                            <td class="fx-td whitespace-nowrap px-3 py-3">
                                <div class="flex justify-end">
                                    <a
                                        href="{{ route('embarques.show', $shipment) }}"
                                        class="inline-flex items-center rounded-xl bg-blue-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-500"
                                    >
                                        Ver embarque
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr x-show="open" x-cloak>
                            <td colspan="7" class="p-0">
                                <div class="border-t border-slate-200 bg-white">
                                    <table class="fx-table w-full text-[13px]">
                                        <thead>
                                            <tr class="bg-slate-50/70">
                                                <th class="fx-th whitespace-nowrap px-3 py-2.5 text-center"></th>
                                                <th colspan="2" class="fx-th whitespace-nowrap px-3 py-2.5">Numero</th>
                                                <th class="fx-th whitespace-nowrap px-3 py-2.5">Estado</th>
                                                <th class="fx-th whitespace-nowrap px-3 py-2.5">Monto</th>
                                                <th class="fx-th whitespace-nowrap px-3 py-2.5">Registrada</th>
                                                <th class="fx-th whitespace-nowrap px-3 py-2.5 text-right"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            @foreach ($visibleInvoices as $invoice)
                                                <tr>
                                                    <td class="fx-td px-3 py-3 text-center"></td>
                                                    <td colspan="2" class="fx-td whitespace-nowrap px-3 py-3 font-semibold text-slate-900">{{ $invoice->numero }}</td>
                                                    <td class="fx-td whitespace-nowrap px-3 py-3">
                                                        <span class="fx-badge {{ $invoice->estado === 'pagada' ? 'bg-emerald-100 text-emerald-700' : ($invoice->estado === 'emitida' ? 'bg-cyan-100 text-cyan-700' : 'bg-amber-100 text-amber-700') }}">
                                                            {{ ucfirst($invoice->estado) }}
                                                        </span>
                                                    </td>
                                                    <td class="fx-td whitespace-nowrap px-3 py-3">S/ {{ number_format((float) $invoice->monto, 2) }}</td>
                                                    <td class="fx-td whitespace-nowrap px-3 py-3">{{ $invoice->created_at->format('d/m/Y H:i') }}</td>
                                                    <td class="fx-td whitespace-nowrap px-3 py-3 text-right">
                                                        <a href="{{ route('facturacion.edit', $invoice) }}" class="text-cyan-700 hover:text-cyan-800">Editar</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr>
                            <td colspan="7" class="fx-td py-10 text-center text-slate-500">
                                {{ $search !== '' ? 'No se encontraron facturas o embarques con ese criterio.' : 'Todavia no hay facturas registradas.' }}
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">{{ $shipments->links() }}</div>
    </div>
</x-app-layout>
