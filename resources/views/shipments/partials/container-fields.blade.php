{{-- Campos de un contenedor. Requiere $container, $prefix y $containerTypes. --}}
@php
    $isNew = ! $container->exists;
    $value = fn (string $field) => $isNew ? old($field, $container->{$field}) : $container->{$field};
@endphp

<div class="mt-4 grid gap-4 sm:grid-cols-2 md:grid-cols-3">
    <div>
        <label class="fx-label" for="{{ $prefix }}_numero">Número (ISO 6346)</label>
        <input class="fx-input font-mono uppercase" id="{{ $prefix }}_numero" name="numero" maxlength="13" placeholder="MSCU1234565"
            value="{{ $value('numero') }}" required>
    </div>
    <div>
        <label class="fx-label" for="{{ $prefix }}_tipo">Tipo</label>
        <select class="fx-select" id="{{ $prefix }}_tipo" name="tipo" required>
            @foreach ($containerTypes as $type => $typeLabel)
                <option value="{{ $type }}" @selected($value('tipo') === $type)>{{ $type }} · {{ $typeLabel }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="fx-label" for="{{ $prefix }}_precinto">Precinto</label>
        <input class="fx-input" id="{{ $prefix }}_precinto" name="precinto" maxlength="50" value="{{ $value('precinto') }}">
    </div>
    <div>
        <label class="fx-label" for="{{ $prefix }}_bultos">Bultos</label>
        <input class="fx-input" id="{{ $prefix }}_bultos" name="bultos" type="number" min="0" step="1" value="{{ $value('bultos') }}">
    </div>
    <div>
        <label class="fx-label" for="{{ $prefix }}_peso_kg">Peso (kg)</label>
        <input class="fx-input" id="{{ $prefix }}_peso_kg" name="peso_kg" type="number" min="0" step="0.01" value="{{ $value('peso_kg') }}">
    </div>
    <div>
        <label class="fx-label" for="{{ $prefix }}_volumen_cbm">Volumen (CBM)</label>
        <input class="fx-input" id="{{ $prefix }}_volumen_cbm" name="volumen_cbm" type="number" min="0" step="0.01" value="{{ $value('volumen_cbm') }}">
    </div>
    <div>
        <x-form-date-picker :id="$prefix.'_fecha_retiro'" name="fecha_retiro" label="Retiro lleno del terminal"
            :value="$isNew ? old('fecha_retiro') : $container->fecha_retiro?->format('Y-m-d')" placeholder="Sin retirar" />
    </div>
    <div>
        <x-form-date-picker :id="$prefix.'_fecha_devolucion'" name="fecha_devolucion" label="Devolución del vacío"
            :value="$isNew ? old('fecha_devolucion') : $container->fecha_devolucion?->format('Y-m-d')" placeholder="Sin devolver" />
    </div>
</div>
