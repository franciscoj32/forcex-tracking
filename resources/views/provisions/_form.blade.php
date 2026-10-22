{{--
    Costo de proveedor de una RO. Lo registra Operaciones.
    Variables: $provision, $suppliers, $concepts, $currencies,
    $shipments (si no hay $fixedShipment), $fixedShipment (opcional), $inDialog (opcional).
--}}
@csrf

@php
    $inDialog = $inDialog ?? false;
    $fixedShipment = $fixedShipment ?? null;
    // Valores anteriores solo si el error vino de este mismo formulario.
    $old = fn (string $key, $default = null) => old('_form', 'provision') === 'provision' ? old($key, $default) : $default;

    $shipmentOptions = $fixedShipment ? collect() : collect($shipments)->map(fn ($item) => [
        'value' => (string) $item->id,
        'label' => $item->nro_orden_trabajo.' · '.$item->client->razon_social,
    ]);
    $supplierOptions = collect($suppliers)->map(fn ($supplier) => [
        'value' => (string) $supplier->id,
        'label' => $supplier->razon_social.' · '.$supplier->typeLabel(),
    ]);
    $conceptOptions = collect($concepts)->map(fn ($concept) => [
        'value' => (string) $concept->id,
        'label' => $concept->nombre,
    ]);
    $status = $old('estado', $provision->estado ?? 'pendiente');
@endphp

<input type="hidden" name="_form" value="provision">
@if ($inDialog)
    <input type="hidden" name="_dialog" value="1">
@endif

<div @class(['space-y-4 p-4', 'fx-card' => ! $inDialog]) x-data="{ status: @js($status) }">
    @if ($inDialog && $errors->any() && old('_form') === 'provision')
        <ul class="list-disc space-y-0.5 rounded-md border border-rose-300 bg-rose-50 py-2 pl-5 pr-3 text-sm text-rose-800" role="alert">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            @if ($fixedShipment)
                <input type="hidden" name="shipment_id" value="{{ $fixedShipment->id }}">
                <x-form-locked label="Embarque" :value="$fixedShipment->nro_orden_trabajo.' · '.$fixedShipment->client?->razon_social" />
            @else
                <x-form-combobox id="shipment_id" name="shipment_id" label="Embarque (RO)" :options="$shipmentOptions"
                    :value="$old('shipment_id', $provision->shipment_id ?? '')" placeholder="Seleccionar embarque"
                    search-placeholder="Buscar embarque o cliente..." empty-text="No hay embarques que coincidan." searchable required />
            @endif
        </div>
        <div>
            <x-form-combobox id="supplier_id" name="supplier_id" label="Proveedor" :options="$supplierOptions"
                :value="$old('supplier_id', $provision->supplier_id ?? '')" placeholder="Seleccionar proveedor"
                search-placeholder="Buscar proveedor..." empty-text="No hay proveedores. Regístralos en Proveedores." searchable required />
            @can('manage-suppliers')
                <a href="{{ route('proveedores.index') }}" class="fx-btn-link-primary mt-1 inline-block text-xs">Gestionar proveedores</a>
            @endcan
        </div>
        <div>
            <x-form-combobox id="rate_concept_id" name="rate_concept_id" label="Concepto" :options="$conceptOptions"
                :value="$old('rate_concept_id', $provision->rate_concept_id ?? '')" placeholder="Seleccionar concepto"
                search-placeholder="Buscar concepto..." searchable required />
        </div>
        <div>
            <label class="fx-label" for="nro_factura_proveedor">N° de factura del proveedor</label>
            <input class="fx-input" id="nro_factura_proveedor" name="nro_factura_proveedor" maxlength="50"
                placeholder="Ej. F001-000123" value="{{ $old('nro_factura_proveedor', $provision->nro_factura_proveedor) }}">
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="fx-label" for="monto">Monto</label>
            <input class="fx-input" id="monto" name="monto" type="number" step="0.01" min="0.01" value="{{ $old('monto', $provision->monto) }}" required>
        </div>
        <div>
            <label class="fx-label" for="moneda">Moneda</label>
            <select class="fx-select" id="moneda" name="moneda">
                @foreach ($currencies as $currency)
                    <option value="{{ $currency }}" @selected($old('moneda', $provision->moneda ?? 'USD') === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-form-date-picker id="fecha" name="fecha" label="Fecha de registro"
                :value="$old('fecha', $provision->fecha?->format('Y-m-d') ?? now()->format('Y-m-d'))" placeholder="Seleccionar fecha" required />
        </div>
        <div>
            <label class="fx-label" for="estado">Estado</label>
            <select class="fx-select" id="estado" name="estado" x-model="status">
                <option value="pendiente">Pendiente de pago</option>
                <option value="pagado">Pagado</option>
            </select>
        </div>
    </div>

    <div class="grid gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2" x-show="status === 'pagado'" x-cloak>
        <div>
            <label class="fx-label" for="nro_operacion_bancaria">N° de operación bancaria</label>
            <input class="fx-input" id="nro_operacion_bancaria" name="nro_operacion_bancaria" maxlength="50"
                value="{{ $old('nro_operacion_bancaria', $provision->nro_operacion_bancaria) }}" x-bind:required="status === 'pagado'">
        </div>
        <div>
            <x-form-date-picker id="fecha_pago" name="fecha_pago" label="Fecha de pago"
                :value="$old('fecha_pago', $provision->fecha_pago?->format('Y-m-d'))" placeholder="Seleccionar fecha" />
        </div>
    </div>

    <div>
        <label class="fx-label" for="descripcion">Detalle (opcional)</label>
        <textarea class="fx-textarea" id="descripcion" name="descripcion" rows="2"
            placeholder="Si lo dejas vacío se usa el nombre del concepto.">{{ $old('descripcion', $provision->descripcion) }}</textarea>
    </div>
</div>

@if ($inDialog)
    <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
        <button type="button" class="fx-btn-secondary" x-on:click="open = false">Cancelar</button>
        <button class="fx-btn-primary" type="submit">Guardar costo</button>
    </div>
@else
    <div class="mt-4 flex gap-2">
        <button class="fx-btn-primary" type="submit">Guardar</button>
        <a href="{{ $provision->shipment_id ? route('embarques.show', ['shipment' => $provision->shipment_id, 'tab' => 'finanzas']) : route('provisiones.index') }}" class="fx-btn-secondary">Cancelar</a>
    </div>
@endif
