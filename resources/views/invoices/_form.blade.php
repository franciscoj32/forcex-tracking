{{--
    Factura al cliente.
    Variables: $invoice (opcional), $shipments (si no hay $fixedShipment),
    $fixedShipment (opcional), $inDialog (opcional).
    Los id llevan prefijo "invoice_" para convivir con el formulario de costos en la misma pagina.
--}}
@csrf

@php
    $inDialog = $inDialog ?? false;
    $fixedShipment = $fixedShipment ?? null;
    $invoice = $invoice ?? new \App\Models\Invoice();
    // Valores anteriores solo si el error vino de este mismo formulario.
    $old = fn (string $key, $default = null) => old('_form', 'invoice') === 'invoice' ? old($key, $default) : $default;

    $shipmentOptions = $fixedShipment ? collect() : collect($shipments)->map(fn ($item) => [
        'value' => (string) $item->id,
        'label' => $item->nro_orden_trabajo.' · '.$item->client->razon_social,
    ]);
@endphp

<input type="hidden" name="_form" value="invoice">
@if ($inDialog)
    <input type="hidden" name="_dialog" value="1">
@endif

<div @class(['space-y-4 p-4', 'fx-card' => ! $inDialog])>
    @if ($inDialog && $errors->any() && old('_form') === 'invoice')
        <ul class="list-disc space-y-0.5 rounded-md border border-rose-300 bg-rose-50 py-2 pl-5 pr-3 text-sm text-rose-800" role="alert">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    @if ($fixedShipment)
        <input type="hidden" name="shipment_id" value="{{ $fixedShipment->id }}">
        <x-form-locked label="Embarque" :value="$fixedShipment->nro_orden_trabajo.' · '.$fixedShipment->client?->razon_social" />
    @else
        <x-form-combobox
            id="invoice_shipment_id"
            name="shipment_id"
            label="Embarque"
            :options="$shipmentOptions"
            :value="$old('shipment_id', $invoice->shipment_id ?? '')"
            placeholder="Seleccionar embarque"
            search-placeholder="Buscar embarque o cliente..."
            empty-text="No hay embarques que coincidan."
            searchable
            required
        />
    @endif
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="fx-label" for="invoice_numero">Número de factura</label>
            <input class="fx-input" id="invoice_numero" name="numero" value="{{ $old('numero', $invoice->numero) }}" placeholder="F001-000123" required>
        </div>
        <div>
            <label class="fx-label" for="invoice_monto">Monto</label>
            <input class="fx-input" id="invoice_monto" name="monto" type="number" step="0.01" min="0" value="{{ $old('monto', $invoice->monto) }}" required>
        </div>
        <div>
            <label class="fx-label" for="invoice_moneda">Moneda</label>
            <select class="fx-select" id="invoice_moneda" name="moneda">
                @foreach (\App\Models\Invoice::CURRENCIES as $currency)
                    <option value="{{ $currency }}" @selected($old('moneda', $invoice->moneda ?? 'PEN') === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="fx-label" for="invoice_estado">Estado</label>
            <select class="fx-select" id="invoice_estado" name="estado" required>
                @foreach (['pendiente' => 'Pendiente de emitir', 'emitida' => 'Emitida, por cobrar', 'pagada' => 'Pagada'] as $value => $label)
                    <option value="{{ $value }}" @selected($old('estado', $invoice->estado ?? 'pendiente') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div>
        <label class="fx-label" for="invoice_archivo_pdf">PDF de la factura (opcional, máx. 5 MB)</label>
        <input class="fx-input" id="invoice_archivo_pdf" name="archivo_pdf" type="file" accept=".pdf">
    </div>
</div>

@if ($inDialog)
    <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
        <button type="button" class="fx-btn-secondary" x-on:click="open = false">Cancelar</button>
        <button class="fx-btn-primary" type="submit">Guardar factura</button>
    </div>
@else
    <div class="mt-4 flex gap-2">
        <button class="fx-btn-primary" type="submit">Guardar</button>
        <a href="{{ route('facturacion.index') }}" class="fx-btn-secondary">Cancelar</a>
    </div>
@endif
