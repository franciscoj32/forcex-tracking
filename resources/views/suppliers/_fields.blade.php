{{-- Campos de proveedor. Requiere $supplier, $prefix y $types. --}}
<div class="mt-4 grid gap-4 sm:grid-cols-2 md:grid-cols-3">
    <div class="sm:col-span-2">
        <label class="fx-label" for="{{ $prefix }}_razon_social">Razón social</label>
        <input class="fx-input" id="{{ $prefix }}_razon_social" name="razon_social" value="{{ $supplier->exists ? $supplier->razon_social : old('razon_social') }}" required>
    </div>
    <div>
        <label class="fx-label" for="{{ $prefix }}_ruc">RUC / Tax ID</label>
        <input class="fx-input" id="{{ $prefix }}_ruc" name="ruc" maxlength="20" value="{{ $supplier->exists ? $supplier->ruc : old('ruc') }}">
    </div>
    <div>
        <label class="fx-label" for="{{ $prefix }}_tipo">Tipo</label>
        <select class="fx-select" id="{{ $prefix }}_tipo" name="tipo">
            @foreach ($types as $type => $label)
                <option value="{{ $type }}" @selected(($supplier->exists ? $supplier->tipo : old('tipo', $supplier->tipo)) === $type)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="fx-label" for="{{ $prefix }}_correo">Correo</label>
        <input class="fx-input" id="{{ $prefix }}_correo" name="correo" type="email" value="{{ $supplier->exists ? $supplier->correo : old('correo') }}">
    </div>
    <div>
        <label class="fx-label" for="{{ $prefix }}_telefono">Teléfono</label>
        <input class="fx-input" id="{{ $prefix }}_telefono" name="telefono" value="{{ $supplier->exists ? $supplier->telefono : old('telefono') }}">
    </div>
    <div class="flex items-end">
        <input type="hidden" name="activo" value="0">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="activo" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/30" @checked($supplier->activo ?? true)>
            Activo (aparece al registrar costos)
        </label>
    </div>
</div>
