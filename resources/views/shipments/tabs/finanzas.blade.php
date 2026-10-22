{{--
    Finanzas de la RO: tarifas de venta, costos de proveedores, facturacion y margen.
    - Tarifas: Admin, Comercial y Customer (Operaciones no ve lo que se cobra).
    - Costos: Admin, Operaciones y Customer.
    - Margen: Admin y el Comercial duenio de la RO.
    Los totales se muestran una sola vez, en la linea de cuentas de arriba.
--}}
@use('App\Support\Money')
@php
    $user = auth()->user();
    $canViewRates = $user->can('viewRates', $shipment);
    $canViewCosts = $user->can('view-provisions');
    $canManageCosts = $user->can('manage-provisions');
    $canViewMargin = $user->can('viewMargin', $shipment);
    $canViewInvoices = $user->can('view-invoices');
    $financials = $shipment->financials();
    $margin = $financials->marginUsd();
    $canEditRates = $user->can('update', $shipment) && \App\Support\ShipmentFieldAccess::canEdit($user, $shipment, 'rates');
    $canManageInvoices = $user->can('manage-invoices');
@endphp

{{-- Ventanas para registrar costo y factura de este embarque --}}
@if ($canManageCosts)
    <x-dialog name="nueva-provision" title="Registrar costo · {{ $shipment->nro_orden_trabajo }}" max-width="max-w-3xl"
              :show="$errors->any() && old('_form') === 'provision'">
        <form method="POST" action="{{ route('provisiones.store') }}" data-disable-native-autocomplete="true">
            @include('provisions._form', \App\Http\Controllers\ProvisionController::formData(
                new \App\Models\Provision(['shipment_id' => $shipment->id, 'moneda' => 'USD', 'estado' => \App\Models\Provision::STATUS_PENDING]),
                withShipments: false,
            ) + ['inDialog' => true, 'fixedShipment' => $shipment])
        </form>
    </x-dialog>
@endif
@if ($canManageInvoices)
    <x-dialog name="nueva-factura" title="Registrar factura · {{ $shipment->nro_orden_trabajo }}" max-width="max-w-3xl"
              :show="$errors->any() && old('_form') === 'invoice'">
        <form method="POST" action="{{ route('facturacion.store') }}" enctype="multipart/form-data" data-disable-native-autocomplete="true">
            @include('invoices._form', ['inDialog' => true, 'fixedShipment' => $shipment])
        </form>
    </x-dialog>
@endif

<div class="space-y-4">
    {{-- Linea de cuentas --}}
    <section class="fx-card grid grid-cols-2 gap-4 p-4 tabular-nums sm:grid-cols-4">
        @if ($canViewRates)
            <div>
                <p class="text-xs text-slate-500">Venta</p>
                <p class="mt-0.5 text-lg font-semibold text-slate-900">{{ Money::format($financials->salesUsd) }}</p>
                <p class="text-xs text-slate-500">{{ Money::formatTotals($financials->salesByCurrency) }}</p>
            </div>
        @endif
        @if ($canViewCosts || $canViewMargin)
            <div>
                <p class="text-xs text-slate-500">Costo</p>
                <p class="mt-0.5 text-lg font-semibold text-slate-900">{{ Money::format($financials->costsUsd) }}</p>
                <p class="text-xs text-slate-500">{{ Money::formatTotals($financials->costsByCurrency) }}</p>
            </div>
        @endif
        @if ($canViewMargin)
            <div>
                <p class="text-xs text-slate-500">Margen</p>
                <p @class(['mt-0.5 text-lg font-semibold', 'text-amber-700' => $margin === null, 'text-rose-700' => $margin !== null && $margin < 0, 'text-slate-900' => $margin !== null && $margin >= 0])>
                    {{ $margin === null ? 'No calculable' : Money::format($margin) }}
                </p>
                <p class="text-xs text-slate-500">
                    {{ $financials->marginPercent() !== null ? $financials->marginPercent().'% de la venta' : ($margin === null ? 'Falta tipo de cambio para montos en S/' : 'Sin venta') }}
                </p>
            </div>
        @endif
        <div>
            <p class="text-xs text-slate-500">Tipo de cambio de la RO</p>
            <p class="mt-0.5 text-lg font-semibold text-slate-900">{{ $financials->exchangeRate ? number_format($financials->exchangeRate, 4) : '—' }}</p>
            <p class="text-xs text-slate-500">S/ por US$, fijo desde la creación</p>
        </div>
    </section>

    @if ($canViewRates)
        <section class="fx-card">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <h2 class="fx-section-title">Tarifas de venta</h2>
                @if ($canEditRates)
                    <a href="{{ route('embarques.edit', $shipment) }}#tarifas" class="fx-btn-link-primary">Editar tarifas</a>
                @endif
            </div>
            @if ($shipment->rates->isEmpty())
                <p class="px-4 py-4 text-slate-500">Sin tarifas.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="fx-table">
                        <thead>
                            <tr>
                                <th class="fx-th">Concepto</th>
                                <th class="fx-th">Observación</th>
                                <th class="fx-th text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($shipment->rates as $rate)
                                <tr>
                                    <td class="fx-td text-slate-900">{{ $rate->concept?->nombre }}</td>
                                    <td class="fx-td text-slate-500">{{ $rate->observacion ?: '—' }}</td>
                                    <td class="fx-td whitespace-nowrap text-right text-slate-900">{{ Money::format((float) $rate->monto, $rate->moneda) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if ($canViewCosts)
        <section class="fx-card">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <h2 class="fx-section-title">Costos de proveedores</h2>
                @if ($canManageCosts)
                    <button type="button" class="fx-btn-link-primary" x-data x-on:click="$dispatch('open-dialog', 'nueva-provision')">Registrar costo</button>
                @endif
            </div>
            @if ($shipment->provisions->isEmpty())
                <p class="px-4 py-4 text-slate-500">Sin costos.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="fx-table">
                        <thead>
                            <tr>
                                <th class="fx-th">Proveedor · concepto</th>
                                <th class="fx-th">Factura</th>
                                <th class="fx-th">Pago</th>
                                <th class="fx-th text-right">Monto</th>
                                @if ($canManageCosts)
                                    <th class="fx-th"><span class="sr-only">Acciones</span></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($shipment->provisions as $provision)
                                <tr>
                                    <td class="fx-td">
                                        <p class="text-slate-900">{{ $provision->supplier?->razon_social ?? 'Sin proveedor' }}</p>
                                        <p class="text-xs text-slate-500">{{ $provision->concept?->nombre ?? $provision->descripcion }}</p>
                                    </td>
                                    <td class="fx-td">{{ $provision->nro_factura_proveedor ?: '—' }}</td>
                                    <td class="fx-td">
                                        @if ($provision->isPaid())
                                            <p class="text-slate-700">Pagado {{ optional($provision->fecha_pago)->format('d/m/Y') }}</p>
                                            <p class="text-xs text-slate-500">Op. {{ $provision->nro_operacion_bancaria ?: '—' }}</p>
                                        @else
                                            <span class="fx-badge bg-amber-100 text-amber-800">Por pagar</span>
                                        @endif
                                    </td>
                                    <td class="fx-td whitespace-nowrap text-right text-slate-900">{{ Money::format((float) $provision->monto, $provision->moneda) }}</td>
                                    @if ($canManageCosts)
                                        <td class="fx-td text-right"><a href="{{ route('provisiones.edit', $provision) }}" class="fx-btn-link-primary">Editar</a></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if ($canViewInvoices)
        <section class="fx-card">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <h2 class="fx-section-title">Facturación al cliente</h2>
                @if ($canManageInvoices)
                    <button type="button" class="fx-btn-link-primary" x-data x-on:click="$dispatch('open-dialog', 'nueva-factura')">Registrar factura</button>
                @endif
            </div>
            @if ($shipment->invoices->isEmpty())
                <p class="px-4 py-4 text-slate-500">Sin facturas.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="fx-table">
                        <thead>
                            <tr>
                                <th class="fx-th">Número</th>
                                <th class="fx-th">Estado</th>
                                <th class="fx-th text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($shipment->invoices as $invoice)
                                <tr>
                                    <td class="fx-td text-slate-900">{{ $invoice->numero }}</td>
                                    <td class="fx-td">
                                        @if ($invoice->estado === 'pagada')
                                            <span class="text-slate-700">Pagada</span>
                                        @else
                                            <span class="fx-badge bg-amber-100 text-amber-800">{{ $invoice->estado === 'emitida' ? 'Por cobrar' : ucfirst($invoice->estado) }}</span>
                                        @endif
                                    </td>
                                    <td class="fx-td whitespace-nowrap text-right text-slate-900">{{ Money::format((float) $invoice->monto, $invoice->moneda) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>
