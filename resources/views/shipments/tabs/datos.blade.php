@php
    $fieldRowClass = 'grid gap-x-3 gap-y-1 md:grid-cols-[140px_minmax(0,1fr)] lg:grid-cols-[148px_minmax(0,1fr)] md:items-start';
    $fieldLabelClass = 'text-sm font-medium leading-5 text-slate-500';
    $fieldValueClass = 'min-w-0 text-sm leading-6 text-slate-900 break-words';
    $fieldValueStrongClass = $fieldValueClass . ' font-semibold';
@endphp

<div class="space-y-6">
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="rounded-3xl border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Ficha del embarque</h3>
            </div>
            <dl class="divide-y divide-slate-100">
                <div class="grid gap-2 px-5 py-3 {{ $fieldRowClass }}">
                    <dt class="{{ $fieldLabelClass }}">Cliente</dt>
                    <dd class="{{ $fieldValueStrongClass }}">{{ $shipment->client->razon_social }}</dd>
                </div>
                <div class="grid gap-2 px-5 py-3 md:grid-cols-2 md:gap-6">
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">BL</dt>
                        <dd class="{{ $fieldValueStrongClass }}">{{ $shipment->bl }}</dd>
                    </div>
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">Routing Order</dt>
                        <dd class="{{ $fieldValueStrongClass }}">{{ $shipment->routing_order }}</dd>
                    </div>
                </div>
                <div class="grid gap-2 px-5 py-3 md:grid-cols-2 md:gap-6">
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">Origen</dt>
                        <dd class="{{ $fieldValueClass }}">{{ $shipment->origen }}</dd>
                    </div>
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">Destino final</dt>
                        <dd class="{{ $fieldValueClass }}">{{ $shipment->destino_final }}</dd>
                    </div>
                </div>
                <div class="grid gap-2 px-5 py-3 md:grid-cols-2 md:gap-6">
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">Puerto de carga</dt>
                        <dd class="{{ $fieldValueClass }}">{{ $shipment->puerto_carga }}</dd>
                    </div>
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">Puerto de descarga</dt>
                        <dd class="{{ $fieldValueClass }}">{{ $shipment->puerto_descarga }}</dd>
                    </div>
                </div>
                <div class="grid gap-2 px-5 py-3 md:grid-cols-2 md:gap-6">
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">Tipo de carga</dt>
                        <dd class="{{ $fieldValueStrongClass }}">{{ $shipment->tipo_carga }}</dd>
                    </div>
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">Naviera</dt>
                        <dd class="{{ $fieldValueClass }}">{{ $shipment->naviera ?: '-' }}</dd>
                    </div>
                </div>
                <div class="grid gap-2 px-5 py-3 md:grid-cols-2 md:gap-6">
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">Consignatario</dt>
                        <dd class="{{ $fieldValueClass }}">{{ $shipment->consignatario ?: '-' }}</dd>
                    </div>
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">Peso / Volumen</dt>
                        <dd class="{{ $fieldValueClass }}">
                            <span class="block">{{ $shipment->peso ? number_format($shipment->peso, 2) . ' kg' : '-' }}</span>
                            <span class="block">{{ $shipment->volumen ? number_format($shipment->volumen, 2) . ' m3' : '-' }}</span>
                        </dd>
                    </div>
                </div>
                <div class="grid gap-2 px-5 py-3 md:grid-cols-2 md:gap-6">
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">ETD</dt>
                        <dd class="{{ $fieldValueClass }}">{{ optional($shipment->fecha_etd)->format('d/m/Y') ?: '-' }}</dd>
                    </div>
                    <div class="{{ $fieldRowClass }}">
                        <dt class="{{ $fieldLabelClass }}">ETA / Descarga</dt>
                        <dd class="{{ $fieldValueClass }}">
                            <span class="block">{{ optional($shipment->fecha_eta)->format('d/m/Y') ?: '-' }}</span>
                            <span class="block">{{ optional($shipment->fecha_descarga)->format('d/m/Y') ?: '-' }}</span>
                        </dd>
                    </div>
                </div>
            </dl>
        </section>
        <div class="space-y-6">
            <div class="hidden rounded-3xl border border-slate-200 p-5 lg:block">
                <h3 class="text-lg font-semibold text-slate-900">Observaciones generales</h3>
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $shipment->observaciones ?: '-' }}</p>
            </div>

            <div class="rounded-3xl border border-slate-200 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Control de endoso</h3>
                    </div>
                    <span class="fx-badge {{ $shipment->estado_endoso === 'completado' ? 'bg-emerald-100 text-emerald-700' : ($shipment->estado_endoso === 'en_proceso' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">
                        {{ ucfirst(str_replace('_', ' ', $shipment->estado_endoso)) }}
                    </span>
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl bg-slate-50 px-4 py-4">
                        <p class="text-sm text-slate-500">Fecha de endoso</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ optional($shipment->fecha_endoso)->format('d/m/Y') ?: 'Sin fecha' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4">
                        <p class="text-sm text-slate-500">Responsable</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $shipment->endorsementResponsible?->name ?: 'Sin asignar' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-4">
                        <p class="text-sm text-slate-500">Documentos de endoso</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $endorsementDocumentsCount }}</p>
                    </div>
                </div>
                <div class="mt-4 rounded-2xl bg-amber-50 px-4 py-4 text-sm text-slate-700">
                    {{ $shipment->observacion_endoso ?: '-' }}
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Usuarios vinculados</h3>
                    </div>
                    <span class="fx-badge bg-slate-100 text-slate-700">{{ $shipment->userAssignments->count() }} usuario(s)</span>
                </div>

                <div class="mt-5 grid gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Responsables</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @forelse ($responsibleAssignments as $assignment)
                                <span class="rounded-full bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-700">
                                    {{ $assignment->user->name }}
                                </span>
                            @empty
                                <p class="text-sm text-slate-500">No hay responsables asignados todavia.</p>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-slate-500">Seguimiento</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @forelse ($trackingAssignments as $assignment)
                                <span class="rounded-full bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">
                                    {{ $assignment->user->name }}
                                </span>
                            @empty
                                <p class="text-sm text-slate-500">No hay usuarios en seguimiento todavia.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (auth()->user()->role !== 'cliente')
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-3xl border border-slate-200">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Provisiones</h3>
                </div>
                <div class="space-y-3 p-5">
                    @forelse ($shipment->provisions as $provision)
                        <div class="rounded-2xl bg-slate-50 px-4 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-slate-900">S/ {{ number_format($provision->monto, 2) }}</p>
                                <span class="fx-badge {{ $provision->estado === 'pagado' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ ucfirst($provision->estado) }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-slate-500">{{ $provision->descripcion }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sin provisiones registradas.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Facturacion</h3>
                </div>
                <div class="space-y-3 p-5">
                    @forelse ($shipment->invoices as $invoice)
                        <div class="rounded-2xl bg-slate-50 px-4 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-slate-900">{{ $invoice->numero }}</p>
                                <span class="fx-badge {{ $invoice->estado === 'pagada' ? 'bg-emerald-100 text-emerald-700' : ($invoice->estado === 'emitida' ? 'bg-cyan-100 text-cyan-700' : 'bg-amber-100 text-amber-700') }}">
                                    {{ ucfirst($invoice->estado) }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-slate-500">S/ {{ number_format($invoice->monto, 2) }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sin facturas registradas.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <div class="rounded-3xl border border-slate-200 p-5 lg:hidden">
        <h3 class="text-lg font-semibold text-slate-900">Observaciones generales</h3>
        <p class="mt-3 text-sm leading-6 text-slate-600">{{ $shipment->observaciones ?: '-' }}</p>
    </div>
</div>
