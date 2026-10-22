{{-- Mezcla FCL / LCL / Aereo: barra apilada fina + numeros con el color de cada tipo. --}}
@php
    $cargoPalette = \App\Support\ChartPalette::CARGO;
    $cargoTotal = max((int) ($counts['total'] ?? 0), 1);
@endphp
<div class="mt-1.5 flex h-1.5 max-w-[12rem] overflow-hidden rounded-full bg-slate-100">
    @foreach ($cargoPalette as $type => $style)
        @if (($counts[$type] ?? 0) > 0)
            <div class="{{ $style['bar'] }}" style="width: {{ round($counts[$type] / $cargoTotal * 100, 2) }}%"></div>
        @endif
    @endforeach
</div>
<p class="mt-1 flex flex-wrap gap-x-3 text-xs text-slate-500">
    @foreach ($cargoPalette as $type => $style)
        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm {{ $style['dot'] }}"></span>{{ $style['label'] }} <span class="tabular-nums text-slate-800">{{ $counts[$type] ?? 0 }}</span></span>
    @endforeach
</p>
