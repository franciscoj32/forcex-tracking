{{-- Logo de la empresa.
     - "dark": fondo oscuro (menu lateral, login). El logo subido va sobre una base
       blanca para que siempre se lea y el nombre en blanco.
     - "large": version grande del login.
     - Si el logo es un emblema (cuadrado o redondo), a tamaño de menu no se lee:
       se muestra junto al nombre de la empresa. En el login va solo y grande. --}}
@props(['dark' => false, 'large' => false])
@php($appSettings = \App\Models\AppSetting::current())

@if ($appSettings->logoUrl() && $appSettings->logoIsEmblem())
    @if ($large)
        <span {{ $attributes->merge(['class' => 'inline-flex rounded-full bg-white p-1.5 shadow-lg shadow-black/30']) }}>
            <img src="{{ $appSettings->logoUrl() }}" alt="{{ $appSettings->company_name }}" class="h-24 w-24 rounded-full object-contain">
        </span>
    @else
        <span {{ $attributes->merge(['class' => 'inline-flex min-w-0 items-center gap-2.5']) }}>
            <span @class(['flex h-10 w-10 shrink-0 items-center justify-center rounded-full', 'bg-white p-0.5' => $dark])>
                <img src="{{ $appSettings->logoUrl() }}" alt="" class="h-full w-full rounded-full object-contain">
            </span>
            <span @class(['truncate text-sm font-semibold leading-tight', 'text-white' => $dark, 'text-slate-900' => ! $dark])>{{ $appSettings->company_name }}</span>
        </span>
    @endif
@elseif ($appSettings->logoUrl())
    @if ($dark)
        <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md bg-white px-2 py-1']) }}>
            <img src="{{ $appSettings->logoUrl() }}" alt="{{ $appSettings->company_name }}" @class(['max-w-[170px] object-contain', 'h-7' => ! $large, 'h-10' => $large])>
        </span>
    @else
        <img src="{{ $appSettings->logoUrl() }}" alt="{{ $appSettings->company_name }}" {{ $attributes->merge(['class' => 'h-9 max-w-[180px] object-contain']) }}>
    @endif
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
        <span @class([
            'flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-xs font-bold',
            'bg-white text-brand-800' => $dark,
            'bg-brand-600 text-brand-contrast' => ! $dark,
        ])>
            {{ mb_substr($appSettings->brand_abbr, 0, 3) }}
        </span>
        <span @class(['truncate text-base font-semibold', 'text-white' => $dark, 'text-slate-900' => ! $dark])>{{ $appSettings->company_name }}</span>
    </span>
@endif
