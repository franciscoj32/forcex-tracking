@csrf

@php
    $shipmentOptions = collect($shipments)
        ->map(fn ($shipment) => [
            'value' => (string) $shipment->id,
            'label' => $shipment->nro_orden_trabajo.' · '.$shipment->client->razon_social,
        ]);
    $statusOptions = collect(['pendiente', 'pagado'])
        ->map(fn ($estado) => ['value' => $estado, 'label' => ucfirst($estado)]);
@endphp

<div class="space-y-5">
    <div>
        <x-form-combobox
            id="shipment_id"
            name="shipment_id"
            label="Embarque"
            :options="$shipmentOptions"
            :value="old('shipment_id', $provision->shipment_id ?? '')"
            placeholder="Seleccionar embarque"
            search-placeholder="Buscar embarque o cliente..."
            empty-text="No hay embarques que coincidan."
            searchable
        />
    </div>
    <div>
        <label class="fx-label" for="descripcion">Descripcion</label>
        <textarea class="fx-textarea" id="descripcion" name="descripcion" rows="4">{{ old('descripcion', $provision->descripcion ?? '') }}</textarea>
    </div>
    <div class="grid gap-5 md:grid-cols-3">
        <div>
            <label class="fx-label" for="monto">Monto</label>
            <input class="fx-input" id="monto" name="monto" type="number" step="0.01" value="{{ old('monto', $provision->monto ?? '') }}">
        </div>
        <div>
            <x-form-combobox
                id="estado"
                name="estado"
                label="Estado"
                :options="$statusOptions"
                :value="old('estado', $provision->estado ?? 'pendiente')"
                placeholder="Seleccionar estado"
            />
        </div>
        <div>
            <x-form-date-picker
                id="fecha"
                name="fecha"
                label="Fecha"
                :value="old('fecha', isset($provision) ? $provision->fecha?->format('Y-m-d') : now()->format('Y-m-d'))"
                placeholder="Seleccionar fecha"
            />
        </div>
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button class="fx-btn-primary" type="submit">Guardar</button>
    <a href="{{ route('provisiones.index') }}" class="fx-btn-secondary">Cancelar</a>
</div>
