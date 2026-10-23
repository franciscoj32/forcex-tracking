{{--
    Formulario de la Pre-alerta de llegada (ventana emergente o pagina completa).
    Variables: las de PreAlertService::formData() y $volver (inicio, ficha o pagina).
    El Customer revisa destinatarios, documentos y el estado de cobro, y la envia o la retiene.
--}}
@use('App\Models\ShipmentPreAlert')
@use('App\Support\Money')
@php
    $eta = $shipment->fecha_eta;
    $vessel = trim(($shipment->nave ?? '').($shipment->viaje ? ' · viaje '.$shipment->viaje : ''));
    $last = $history->first();
    // Si vuelve con errores, se respeta lo que el usuario marco (aunque haya desmarcado todo).
    $fromOld = old('_form') === 'pre_alerta' && (int) old('_shipment') === $shipment->id;
    $sendClient = $fromOld ? (bool) old('enviar_cliente') : $clientEmails !== [];
    $checkedSuppliers = $fromOld
        ? array_map('intval', (array) old('supplier_ids', []))
        : $suppliers->filter(fn ($o) => $o['checked'])->map(fn ($o) => $o['supplier']->id)->values()->all();
    $checkedDocuments = $fromOld
        ? array_map('intval', (array) old('document_ids', []))
        : $documents->filter(fn ($o) => $o['checked'])->map(fn ($o) => $o['document']->id)->values()->all();
    $extras = $fromOld ? (array) old('correos_extra', []) : [];
    $formatSize = fn (?int $bytes) => $bytes === null ? '' : ($bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : max(1, (int) round($bytes / 1024)).' KB');
    $totals = fn (array $rows) => $rows !== []
        ? collect($rows)->map(fn ($row, $currency) => Money::format($row['total'], $currency).' ('.$row['count'].')')->join(' + ')
        : 'Nada pendiente';
    $facts = array_filter([
        'ETA' => $eta?->format('d/m/Y') ?? 'Sin ETA',
        ($shipment->isAirCargo() ? 'AWB' : 'BL') => $shipment->bl,
        ($shipment->isAirCargo() ? 'Vuelo' : 'Nave') => $vessel ?: null,
        'Ruta' => trim(($shipment->puerto_carga ?? '').' → '.($shipment->puerto_descarga ?? '')),
        ($shipment->containers->count() === 1 ? 'Contenedor' : 'Contenedores') => $shipment->containers->map(fn ($c) => "{$c->numero} ({$c->tipo})")->join(', ') ?: null,
        'Almacén' => $shipment->almacen_direccionamiento,
    ]);
@endphp

<div class="grid items-start gap-4 p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_19rem]">
    <div class="space-y-4">
        {{-- Avisos --}}
        @if ($blockReason)
            <div class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2.5 text-sm text-amber-900" role="alert">
                <p class="font-medium">Por ahora no se puede enviar.</p>
                <p>{{ $blockReason }}</p>
                @if ($pendingEtaChange)
                    <a href="{{ route('embarques.show', ['shipment' => $shipment, 'tab' => 'datos']) }}#cambios-fecha" class="mt-1 inline-block font-medium underline">Ir a validar el cambio de ETA</a>
                @endif
            </div>
        @elseif ($last?->isHeld())
            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                <span class="font-medium text-slate-900">Retenida</span> por {{ $last->user->name }} el {{ $last->created_at->format('d/m H:i') }}: {{ $last->motivo_retencion }}
            </div>
        @elseif ($last?->isSent() && $eta && ! $last->eta_enviada?->isSameDay($eta))
            <div class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                Se envió con ETA {{ $last->eta_enviada?->format('d/m/Y') }}. La ETA ahora es {{ $eta->format('d/m/Y') }}: reenvíala con la nueva fecha.
            </div>
        @endif

        {{-- Datos del aviso, en una linea --}}
        <dl class="flex flex-wrap gap-x-5 gap-y-1 rounded-md bg-slate-50 px-3 py-2.5 text-sm">
            @foreach ($facts as $label => $value)
                <div><dt class="inline text-slate-500">{{ $label }}:</dt> <dd class="inline font-medium text-slate-900">{{ $value }}</dd></div>
            @endforeach
        </dl>

        @unless ($blockReason)
            <form method="POST" action="{{ route('embarques.pre-alerta.store', $shipment) }}" class="space-y-4"
                  x-data="{ docs: @js($checkedDocuments), sizes: @js($documents->mapWithKeys(fn ($o) => [$o['document']->id => (int) $o['document']->peso])), max: {{ ShipmentPreAlert::MAX_ATTACHMENT_BYTES }},
                            get total() { return this.docs.reduce((sum, id) => sum + (this.sizes[id] || 0), 0) } }">
                @csrf
                <input type="hidden" name="_form" value="pre_alerta">
                <input type="hidden" name="_shipment" value="{{ $shipment->id }}">
                <input type="hidden" name="volver" value="{{ $volver }}">

                {{-- Destinatarios --}}
                <fieldset>
                    <legend class="fx-label">Destinatarios <span class="font-normal text-slate-500">· cada uno recibe su propio correo; si responden, te llega a ti</span></legend>
                    <div class="mt-2 divide-y divide-slate-100 rounded-md border border-slate-200">
                        <label class="flex items-start gap-3 px-3 py-2">
                            <input type="checkbox" name="enviar_cliente" value="1" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-600/30"
                                @checked($sendClient && $clientEmails !== []) @disabled($clientEmails === [])>
                            <span class="min-w-0 text-sm">
                                <span class="font-medium text-slate-900">Cliente</span>
                                <span class="block truncate text-slate-500">{{ $clientEmails !== [] ? implode(', ', $clientEmails) : 'Sin correos de contacto registrados.' }}</span>
                            </span>
                        </label>
                        @foreach ($suppliers as $option)
                            @php $supplier = $option['supplier']; @endphp
                            <label class="flex items-start gap-3 px-3 py-2">
                                <input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-600/30"
                                    @checked(in_array($supplier->id, $checkedSuppliers, true)) @disabled(blank($supplier->correo))>
                                <span class="min-w-0 text-sm">
                                    <span class="font-medium text-slate-900">{{ $supplier->razon_social }}</span>
                                    <span class="text-slate-500">· {{ $supplier->typeLabel() }}</span>
                                    <span class="block truncate {{ $supplier->correo ? 'text-slate-500' : 'text-amber-700' }}">{{ $supplier->correo ?: 'Sin correo registrado' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @if ($missingSuppliers !== [])
                        <div class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            @foreach ($missingSuppliers as $missing)
                                <p>{{ $missing }}</p>
                            @endforeach
                            @can('manage-suppliers')
                                <a href="{{ route('proveedores.index') }}" class="font-medium underline">Ir a Proveedores</a>
                            @else
                                <p class="mt-0.5">Pide a Operaciones que registre al proveedor en las provisiones de la RO o su correo en Proveedores.</p>
                            @endcan
                        </div>
                    @endif
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        @for ($i = 0; $i < ShipmentPreAlert::MAX_EXTRA_EMAILS; $i++)
                            <input type="email" name="correos_extra[]" value="{{ $extras[$i] ?? '' }}" class="fx-input mt-0" placeholder="Otro correo (opcional)" aria-label="Correo adicional {{ $i + 1 }}">
                        @endfor
                    </div>
                </fieldset>

                {{-- Documentos --}}
                <fieldset>
                    <legend class="flex w-full items-center justify-between gap-3">
                        <span class="fx-label">Documentos para la agencia <span class="font-normal text-slate-500">· no van al cliente</span></span>
                        <span class="text-xs tabular-nums" :class="total > max ? 'font-semibold text-rose-700' : 'text-slate-500'"
                              x-text="(total / 1048576).toFixed(1) + ' de {{ $maxAttachmentMb }} MB'"></span>
                    </legend>
                    @if ($documents->isEmpty())
                        <p class="mt-1 text-sm text-slate-500">No hay BL, Commercial Invoice ni Packing List subidos. Puedes enviarla sin adjuntos.</p>
                    @else
                        <div class="mt-2 grid gap-x-4 gap-y-2 sm:grid-cols-2">
                            @foreach ($documents as $option)
                                @php $document = $option['document']; @endphp
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="document_ids[]" value="{{ $document->id }}" x-model.number="docs"
                                        class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-600/30">
                                    <span class="min-w-0 text-sm">
                                        <span class="font-medium text-slate-900">{{ $document->tipo_documento }}</span>
                                        @if ($option['note'])
                                            <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800">{{ $option['note'] }}</span>
                                        @endif
                                        <span class="block truncate text-slate-500">{{ $document->nombre_original }} · {{ $formatSize($document->peso) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </fieldset>

                <div>
                    <label class="fx-label" for="nota-{{ $shipment->id }}">Indicaciones (opcional, van en todos los correos)</label>
                    <textarea class="fx-textarea" id="nota-{{ $shipment->id }}" name="nota" rows="2" maxlength="1000"
                        placeholder="Ej. Favor de iniciar el despacho anticipado.">{{ $fromOld ? old('nota') : '' }}</textarea>
                </div>

                <button type="submit" class="fx-btn-primary inline-flex w-full items-center justify-center gap-2 sm:w-auto" :disabled="total > max">
                    <x-nav-icon name="send" class="h-4 w-4" />
                    Enviar pre-alerta a externos
                </button>
            </form>
        @endunless
    </div>

    <div class="space-y-4">
        {{-- Estado de cobro: para decidir si se retiene --}}
        <section class="rounded-md border border-slate-200">
            <h4 class="border-b border-slate-200 px-3 py-2 text-sm font-semibold text-slate-900">Estado de cobro</h4>
            <dl class="space-y-2 px-3 py-2.5 text-sm">
                <div><dt class="text-slate-500">Condición de esta RO</dt><dd class="font-medium text-slate-900">{{ $credit['credit_label'] }}</dd></div>
                <div><dt class="text-slate-500">Por cobrar de esta RO</dt><dd class="font-medium text-slate-900">{{ $totals($credit['this_ro']) }}</dd></div>
                <div><dt class="text-slate-500">Por cobrar del cliente</dt><dd class="font-medium text-slate-900">{{ $totals($credit['client']) }}</dd></div>
            </dl>
            @if ($credit['is_cash'] && $credit['this_ro'] !== [])
                <p class="mx-3 mb-2.5 rounded bg-amber-50 px-2.5 py-1.5 text-sm text-amber-900">Es al contado y tiene facturas por cobrar. Considera retenerla hasta que pague.</p>
            @endif
        </section>

        @unless ($blockReason)
            <form method="POST" action="{{ route('embarques.pre-alerta.retener', $shipment) }}" class="space-y-2 rounded-md border border-slate-200 p-3">
                @csrf
                <input type="hidden" name="_form" value="pre_alerta">
                <input type="hidden" name="_shipment" value="{{ $shipment->id }}">
                <input type="hidden" name="volver" value="{{ $volver }}">
                <label class="fx-label" for="motivo-{{ $shipment->id }}">Retener</label>
                <textarea class="fx-textarea mt-0" id="motivo-{{ $shipment->id }}" name="motivo_retencion" rows="2" maxlength="500" required
                    placeholder="Motivo, ej. facturas pendientes de pago">{{ $fromOld ? old('motivo_retencion') : '' }}</textarea>
                <button type="submit" class="fx-btn-secondary w-full">Retener pre-alerta</button>
                <p class="text-xs text-slate-500">No se envía nada. El aviso sigue visible hasta que la envíes.</p>
            </form>
        @endunless

        @if ($history->isNotEmpty())
            <details class="rounded-md border border-slate-200">
                <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-slate-700">Historial ({{ $history->count() }})</summary>
                <ul class="divide-y divide-slate-100 border-t border-slate-200 text-sm">
                    @foreach ($history as $item)
                        <li class="px-3 py-2">
                            @if ($item->isSent())
                                <span class="font-medium text-accent-700">Enviada</span> con ETA {{ $item->eta_enviada?->format('d/m/Y') }} a {{ $item->recipientsSummary() }}
                            @else
                                <span class="font-medium text-slate-900">Retenida:</span> {{ $item->motivo_retencion }}
                            @endif
                            <span class="block text-xs text-slate-500">{{ $item->user->name }} · {{ $item->created_at->format('d/m/Y H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</div>
