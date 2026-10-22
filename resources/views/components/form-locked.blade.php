{{--
    Dato de solo lectura dentro del formulario de embarque.
    Se usa cuando el rol no puede editar ese campo (ver App\Support\ShipmentFieldAccess).
    No envia ningun valor al servidor.
--}}
@props([
    'label',
    'value' => null,
])

<div {{ $attributes }}>
    <span class="fx-label text-slate-500">{{ $label }}</span>
    <p class="mt-1 flex min-h-10 items-center break-words text-sm text-slate-900">{{ filled($value) ? $value : '—' }}</p>
</div>
