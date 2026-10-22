{{-- Campos de un contacto. Requiere $contact y $prefixId (para ids unicos). --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <div>
        <label class="fx-label" for="{{ $prefixId }}_nombre">Nombre</label>
        <input class="fx-input" id="{{ $prefixId }}_nombre" name="nombre" value="{{ $contact->nombre }}" required>
    </div>
    <div>
        <label class="fx-label" for="{{ $prefixId }}_correo">Correo</label>
        <input class="fx-input" id="{{ $prefixId }}_correo" name="correo" type="email" value="{{ $contact->correo }}" required>
    </div>
    <div>
        <label class="fx-label" for="{{ $prefixId }}_telefono">Celular (para WhatsApp)</label>
        <input class="fx-input" id="{{ $prefixId }}_telefono" name="telefono" value="{{ $contact->telefono }}">
    </div>
    <div>
        <label class="fx-label" for="{{ $prefixId }}_cargo">Cargo</label>
        <input class="fx-input" id="{{ $prefixId }}_cargo" name="cargo" value="{{ $contact->cargo }}">
    </div>
    <div>
        <label class="fx-label" for="{{ $prefixId }}_area">Area</label>
        <select class="fx-select" id="{{ $prefixId }}_area" name="area">
            @foreach (\App\Models\ClientContact::AREA_LABELS as $area => $areaLabel)
                <option value="{{ $area }}" @selected($contact->area === $area)>{{ $areaLabel }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex flex-col justify-end gap-2 text-sm text-slate-700">
        <input type="hidden" name="recibe_tracking" value="0">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="recibe_tracking" value="1" class="rounded border-slate-300" @checked($contact->recibe_tracking)>
            Recibe avisos de la carga
        </label>
        <input type="hidden" name="recibe_facturacion" value="0">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="recibe_facturacion" value="1" class="rounded border-slate-300" @checked($contact->recibe_facturacion)>
            Recibe facturacion
        </label>
        <input type="hidden" name="es_principal" value="0">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="es_principal" value="1" class="rounded border-slate-300" @checked($contact->es_principal)>
            Contacto principal
        </label>
    </div>
</div>
