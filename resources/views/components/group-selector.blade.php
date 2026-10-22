{{-- Selector "Agrupar por". $options: clave => etiqueta; $urlFor: fn (clave) => url. --}}
@props(['options', 'current', 'urlFor'])

<div {{ $attributes->class('flex items-center gap-2') }}>
    <span class="text-sm text-slate-500">Agrupar por</span>
    <div class="inline-flex rounded-md border border-slate-300 bg-white p-0.5" role="group" aria-label="Agrupar por">
        @foreach ($options as $key => $label)
            <a href="{{ $urlFor($key) }}"
               @class([
                   'rounded px-3 py-1.5 text-sm font-medium transition-colors',
                   'bg-brand-600 text-brand-contrast' => $current === $key,
                   'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => $current !== $key,
               ])
               @if ($current === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
</div>
