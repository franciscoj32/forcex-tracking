@csrf

@php
    $shipmentOptions = collect($shipments)
        ->map(fn ($shipment) => [
            'value' => (string) $shipment->id,
            'label' => $shipment->nro_orden_trabajo.' · '.$shipment->client->razon_social,
        ]);
    $statusOptions = collect(['pendiente', 'emitida', 'pagada'])
        ->map(fn ($estado) => ['value' => $estado, 'label' => ucfirst($estado)]);
@endphp

<div class="space-y-5">
    <div>
        <x-form-combobox
            id="shipment_id"
            name="shipment_id"
            label="Embarque"
            :options="$shipmentOptions"
            :value="old('shipment_id', $invoice->shipment_id ?? '')"
            placeholder="Seleccionar embarque"
            search-placeholder="Buscar embarque o cliente..."
            empty-text="No hay embarques que coincidan."
            searchable
            required
        />
    </div>
    <div class="grid gap-5 md:grid-cols-3">
        <div>
            <label class="fx-label" for="numero">Numero</label>
            <input class="fx-input" id="numero" name="numero" value="{{ old('numero', $invoice->numero ?? '') }}">
        </div>
        <div>
            <label class="fx-label" for="monto">Monto</label>
            <input class="fx-input" id="monto" name="monto" type="number" step="0.01" value="{{ old('monto', $invoice->monto ?? '') }}">
        </div>
        <div>
            <x-form-combobox
                id="estado"
                name="estado"
                label="Estado"
                :options="$statusOptions"
                :value="old('estado', $invoice->estado ?? '')"
                placeholder="Seleccionar estado"
                required
            />
        </div>
    </div>
    <div>
        <label class="fx-label" for="archivo_pdf">PDF de factura</label>
        <input class="fx-input" id="archivo_pdf" name="archivo_pdf" type="file" accept=".pdf">
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button class="fx-btn-primary" type="submit">Guardar</button>
    <a href="{{ route('facturacion.index') }}" class="fx-btn-secondary">Cancelar</a>
</div>
