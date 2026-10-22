{{--
    Pre-alerta de llegada: el Customer revisa destinatarios, documentos y el
    estado de cobro del cliente, y decide si la envia o la retiene.
--}}
@use('App\Support\Money')
@php
    $eta = $shipment->fecha_eta;
    $vessel = trim(($shipment->nave ?? '').($shipment->viaje ? ' · viaje '.$shipment->viaje : ''));
    $last = $history->first();
    $hasOpenInvoices = $credit['client'] !== [];
    $oldSuppliers = collect(old('supplier_ids', $suppliers->filter(fn ($o) => $o['checked'])->map(fn ($o) => $o['supplier']->id)->all()))->map(fn ($id) => (int) $id)->all();
    $oldDocuments = collect(old('document_ids', $documents->filter(fn ($o) => $o['checked'])->map(fn ($o) => $o['document']->id)->all()))->map(fn ($id) => (int) $id)->all();
    $oldExtras = old('correos_extra', ['', '', '']);
    $formatSize = fn (?int $bytes) => $bytes === null ? '' : ($bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : max(1, (int) round($bytes / 1024)).' KB');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0">
            <h1>Pre-alerta de llegada</h1>
            <p class="text-sm font-normal text-slate-500">
                <a href="{{ route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking']) }}" class="hover:underline">{{ $shipment->routing_order }}</a>
                · {{ $shipment->client->razon_social }}
            </p>
        </div>
    </x-slot>

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-4">
            @if ($blockReason)
                <section class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                    <p class="font-medium">Por ahora no se puede enviar.</p>
                    <p>{{ $blockReason }}</p>
                    @if ($pendingEtaChange)
                        <a href="{{ route('embarques.show', $shipment) }}" class="mt-1 inline-block font-medium underline">Ir a validar el cambio de ETA</a>
                    @endif
                </section>
            @endif

            @if ($last?->isHeld())
                <section class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <span class="font-medium text-slate-900">Retenida</span> por {{ $last->user->name }} el {{ $last->created_at->format('d/m H:i') }}: {{ $last->motivo_retencion }}
                </section>
            @elseif ($last?->isSent() && $eta && ! $last->eta_enviada?->isSameDay($eta))
                <section class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Se envió con ETA {{ $last->eta_enviada?->format('d/m/Y') }}. La ETA ahora es {{ $eta->format('d/m/Y') }}: reenvíala con la nueva fecha.
                </section>
            @endif

            {{-- Datos del aviso --}}
            <section class="fx-card">
                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Datos del aviso</h2>
                <dl class="grid gap-x-6 gap-y-2 p-4 text-sm sm:grid-cols-2">
                    @foreach (array_filter([
                        'Llegada estimada (ETA)' => $eta?->format('d/m/Y') ?? 'Sin ETA',
                        ($shipment->isAirCargo() ? 'AWB' : 'BL') => $shipment->bl,
                        ($shipment->isAirCargo() ? 'Vuelo' : 'Nave') => $vessel ?: null,
                        ($shipment->isAirCargo() ? 'Aerolínea' : 'Naviera') => $shipment->naviera,
                        'Ruta' => trim(($shipment->puerto_carga ?? '').' → '.($shipment->puerto_descarga ?? '')),
                        'Consignatario' => $shipment->consignatario,
                        ($shipment->containers->count() === 1 ? 'Contenedor' : 'Contenedores') => $shipment->containers->map(fn ($c) => "{$c->numero} ({$c->tipo})")->join(', ') ?: null,
                        'Peso · volumen' => collect([
                            $shipment->peso !== null ? number_format((float) $shipment->peso, 2).' kg' : null,
                            $shipment->volumen !== null ? number_format((float) $shipment->volumen, 2).' m³' : null,
                        ])->filter()->join(' · ') ?: null,
                        'Almacén de direccionamiento' => $shipment->almacen_direccionamiento,
                    ]) as $label => $value)
                        <div class="flex justify-between gap-3 border-b border-slate-100 pb-1.5 sm:block sm:border-0 sm:pb-0">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="font-medium text-slate-900 sm:mt-0.5">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @unless ($blockReason)
                <form method="POST" action="{{ route('embarques.pre-alerta.store', $shipment) }}" class="space-y-4"
                      x-data="{ docs: @js($oldDocuments), sizes: @js($documents->mapWithKeys(fn ($o) => [$o['document']->id => (int) $o['document']->peso])), max: {{ \App\Models\ShipmentPreAlert::MAX_ATTACHMENT_BYTES }},
                                get total() { return this.docs.reduce((sum, id) => sum + (this.sizes[id] || 0), 0) } }">
                    @csrf

                    {{-- Destinatarios --}}
                    <section class="fx-card">
                        <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Destinatarios</h2>
                        <div class="space-y-4 p-4">
                            <p class="text-sm text-slate-500">Cada uno recibe su propio correo: nadie ve los correos de los demás. Si responden, te llega a ti.</p>

                            <label class="flex items-start gap-3">
                                <input type="checkbox" name="enviar_cliente" value="1" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-600/30"
                                    @checked(old('enviar_cliente', $clientEmails !== [])) @disabled($clientEmails === [])>
                                <span class="text-sm">
                                    <span class="font-medium text-slate-900">Cliente</span>
                                    <span class="block text-slate-500">{{ $clientEmails !== [] ? implode(', ', $clientEmails) : 'El cliente no tiene correos de contacto registrados.' }}</span>
                                </span>
                            </label>

                            @foreach ($suppliers as $option)
                                @php $supplier = $option['supplier']; @endphp
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-600/30"
                                        @checked(in_array($supplier->id, $oldSuppliers, true)) @disabled(blank($supplier->correo))>
                                    <span class="text-sm">
                                        <span class="font-medium text-slate-900">{{ $supplier->razon_social }}</span>
                                        <span class="text-slate-500">· {{ $supplier->typeLabel() }}</span>
                                        <span class="block {{ $supplier->correo ? 'text-slate-500' : 'text-amber-700' }}">{{ $supplier->correo ?: 'Sin correo registrado' }}</span>
                                    </span>
                                </label>
                            @endforeach

                            @if ($missingSuppliers !== [])
                                <div class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                    @foreach ($missingSuppliers as $missing)
                                        <p>{{ $missing }}</p>
                                    @endforeach
                                    @can('manage-suppliers')
                                        <a href="{{ route('proveedores.index') }}" class="font-medium underline">Ir a Proveedores</a>
                                    @else
                                        <p class="mt-1">Pide a Operaciones que registre el proveedor en las provisiones de la RO o su correo en Proveedores.</p>
                                    @endcan
                                </div>
                            @endif

                            <div>
                                <p class="fx-label">Otros correos (opcional, hasta {{ \App\Models\ShipmentPreAlert::MAX_EXTRA_EMAILS }})</p>
                                <div class="mt-1 grid gap-2 sm:grid-cols-3">
                                    @for ($i = 0; $i < \App\Models\ShipmentPreAlert::MAX_EXTRA_EMAILS; $i++)
                                        <input type="email" name="correos_extra[]" value="{{ $oldExtras[$i] ?? '' }}" class="fx-input mt-0" placeholder="despachador@empresa.com" aria-label="Correo adicional {{ $i + 1 }}">
                                    @endfor
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Documentos --}}
                    <section class="fx-card">
                        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                            <h2 class="fx-section-title">Documentos para la agencia</h2>
                            <span class="text-xs tabular-nums" :class="total > max ? 'font-semibold text-rose-700' : 'text-slate-500'"
                                  x-text="(total / 1048576).toFixed(1) + ' de {{ $maxAttachmentMb }} MB'"></span>
                        </div>
                        <div class="space-y-3 p-4">
                            @forelse ($documents as $option)
                                @php $document = $option['document']; @endphp
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="document_ids[]" value="{{ $document->id }}" x-model.number="docs"
                                        class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-600/30">
                                    <span class="text-sm">
                                        <span class="font-medium text-slate-900">{{ $document->tipo_documento }}</span>
                                        @if ($option['note'])
                                            <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800">{{ $option['note'] }}</span>
                                        @endif
                                        <span class="block text-slate-500">{{ $document->nombre_original }} · {{ $formatSize($document->peso) }}</span>
                                    </span>
                                </label>
                            @empty
                                <p class="text-sm text-slate-500">No hay BL, Commercial Invoice ni Packing List subidos. Puedes subirlos en la pestaña Documentos o enviar la pre-alerta sin adjuntos.</p>
                            @endforelse
                            <p class="text-xs text-slate-500">Solo van adjuntos en los correos a proveedores y otros correos, no en el del cliente.</p>
                        </div>
                    </section>

                    {{-- Nota y envio --}}
                    <section class="fx-card">
                        <div class="space-y-3 p-4">
                            <div>
                                <label class="fx-label" for="nota">Indicaciones (opcional, van en todos los correos)</label>
                                <textarea class="fx-textarea" id="nota" name="nota" rows="2" maxlength="1000"
                                    placeholder="Ej. Favor de iniciar el despacho anticipado. El retiro se coordina para el día del arribo.">{{ old('nota') }}</textarea>
                            </div>
                            <button type="submit" class="fx-btn-primary inline-flex items-center gap-2" :disabled="total > max">
                                <x-nav-icon name="send" class="h-4 w-4" />
                                Enviar pre-alerta a externos
                            </button>
                        </div>
                    </section>
                </form>
            @endunless
        </div>

        <div class="space-y-4 lg:sticky lg:top-20">
            {{-- Estado de cobro: para decidir si se retiene --}}
            <section class="fx-card">
                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Estado de cobro</h2>
                <div class="space-y-3 p-4 text-sm">
                    <p><span class="text-slate-500">Condición de esta RO:</span> <span class="font-medium text-slate-900">{{ $credit['credit_label'] }}</span></p>
                    <div>
                        <p class="text-slate-500">Por cobrar de esta RO</p>
                        <p class="font-medium text-slate-900">
                            {{ $credit['this_ro'] !== [] ? collect($credit['this_ro'])->map(fn ($row, $currency) => Money::format($row['total'], $currency).' ('.$row['count'].')')->join(' + ') : 'Nada pendiente' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-slate-500">Por cobrar del cliente (todas sus RO)</p>
                        <p class="font-medium text-slate-900">
                            {{ $hasOpenInvoices ? collect($credit['client'])->map(fn ($row, $currency) => Money::format($row['total'], $currency).' ('.$row['count'].')')->join(' + ') : 'Nada pendiente' }}
                        </p>
                    </div>
                    @if ($credit['is_cash'] && $credit['this_ro'] !== [])
                        <p class="rounded-md bg-amber-50 px-3 py-2 text-amber-900">Es al contado y tiene facturas por cobrar. Considera retener la pre-alerta hasta que pague.</p>
                    @endif
                    <p class="text-xs text-slate-500">Se cuentan las facturas pendientes o emitidas. Las facturas no tienen fecha de vencimiento, así que no se distingue lo vencido.</p>
                </div>
            </section>

            @unless ($blockReason)
                <section class="fx-card">
                    <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Retener</h2>
                    <form method="POST" action="{{ route('embarques.pre-alerta.retener', $shipment) }}" class="space-y-3 p-4">
                        @csrf
                        <div>
                            <label class="fx-label" for="motivo_retencion">Motivo</label>
                            <textarea class="fx-textarea" id="motivo_retencion" name="motivo_retencion" rows="2" maxlength="500" required
                                placeholder="Ej. Facturas pendientes de pago">{{ old('motivo_retencion') }}</textarea>
                        </div>
                        <button type="submit" class="fx-btn-secondary w-full">Retener pre-alerta</button>
                        <p class="text-xs text-slate-500">No se envía nada. La alerta sigue en tu Inicio con el motivo hasta que la envíes.</p>
                    </form>
                </section>
            @endunless

            <section class="fx-card">
                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Historial</h2>
                @forelse ($history as $item)
                    <div class="border-b border-slate-100 px-4 py-2.5 text-sm last:border-0">
                        <p class="text-slate-800">
                            @if ($item->isSent())
                                <span class="font-medium text-accent-700">Enviada</span> con ETA {{ $item->eta_enviada?->format('d/m/Y') }} a {{ $item->recipientsSummary() }}
                            @else
                                <span class="font-medium text-slate-900">Retenida:</span> {{ $item->motivo_retencion }}
                            @endif
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $item->user->name }} · {{ $item->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                @empty
                    <p class="px-4 py-3 text-sm text-slate-500">Todavía no se envió ni se retuvo.</p>
                @endforelse
            </section>
        </div>
    </div>
</x-app-layout>
