@csrf

@php
    $statusOptions = collect(['activo', 'inactivo'])
        ->map(fn ($estado) => ['value' => $estado, 'label' => ucfirst($estado)]);
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="fx-label" for="razon_social">Razón social</label>
        <input class="fx-input" id="razon_social" name="razon_social" value="{{ old('razon_social', $client->razon_social ?? '') }}" required>
    </div>
    <div>
        <label class="fx-label" for="ruc">RUC</label>
        <input class="fx-input" id="ruc" name="ruc" value="{{ old('ruc', $client->ruc ?? '') }}" required>
    </div>
    <div>
        <label class="fx-label" for="correo">Correo</label>
        <input class="fx-input" id="correo" name="correo" type="email" value="{{ old('correo', $client->correo ?? '') }}">
    </div>
    <div>
        <label class="fx-label" for="telefono">Teléfono</label>
        <input class="fx-input" id="telefono" name="telefono" value="{{ old('telefono', $client->telefono ?? '') }}">
    </div>
    <div class="md:col-span-2">
        <label class="fx-label" for="direccion">Dirección</label>
        <input class="fx-input" id="direccion" name="direccion" value="{{ old('direccion', $client->direccion ?? '') }}">
    </div>
    <div>
        <x-form-combobox
            id="estado"
            name="estado"
            label="Estado"
            :options="$statusOptions"
            :value="old('estado', $client->estado ?? 'activo')"
            placeholder="Seleccionar estado"
        />
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button class="fx-btn-primary" type="submit">Guardar</button>
    <a href="{{ route('clientes.index') }}" class="fx-btn-secondary">Cancelar</a>
</div>
