{{--
    Seccion del formulario de embarque.
    Si el rol no puede editar ningun campo de la seccion, se muestra plegada
    como "solo lectura" para que el formulario muestre primero lo que le toca.
--}}
@props([
    'title',
    'readonly' => false,
    'id' => null,
])

@if ($readonly)
    <details {{ $attributes->class('fx-card group scroll-mt-20') }} @if ($id) id="{{ $id }}" @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
            <span class="fx-section-title">{{ $title }} <span class="ml-1 text-sm font-normal text-slate-500">solo lectura</span></span>
            <span class="text-sm text-slate-500 group-open:hidden">Mostrar</span>
            <span class="hidden text-sm text-slate-500 group-open:inline">Ocultar</span>
        </summary>
        <div class="border-t border-slate-200 p-4">{{ $slot }}</div>
    </details>
@else
    <section {{ $attributes->class('fx-card scroll-mt-20') }} @if ($id) id="{{ $id }}" @endif>
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
            <h2 class="fx-section-title">{{ $title }}</h2>
            @isset($aside)
                <div class="text-sm text-slate-600">{{ $aside }}</div>
            @endisset
        </div>
        <div class="p-4">{{ $slot }}</div>
    </section>
@endif
