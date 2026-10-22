{{--
    Fila cabecera de un grupo (Provisiones / Facturacion). Va dentro de un <tbody x-data="{ open }">.
    $group: objeto de App\Support\GroupedList con label, subtitle, url, documentos, abiertos, totals.
    $colspan: columnas antes de "Monto"; $trailing: columnas despues de "Monto".
--}}
@props(['group', 'colspan', 'trailing' => 0, 'noun' => ['documento', 'documentos'], 'openLabel' => 'abiertos'])
@use('App\Support\Money')

<tr class="bg-slate-50/80">
    <td class="fx-td py-2.5" colspan="{{ $colspan }}">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <button type="button" class="flex min-w-0 items-center gap-2 text-left" x-on:click="open = ! open" :aria-expanded="open">
                <span class="inline-block w-3 text-slate-400 transition-transform" :class="open && 'rotate-90'" aria-hidden="true">›</span>
                <span class="min-w-0">
                    <span class="block truncate font-semibold text-slate-900">{{ $group->label }}</span>
                    @if ($group->subtitle)
                        <span class="block truncate text-xs text-slate-500">{{ $group->subtitle }}</span>
                    @endif
                </span>
            </button>
            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200">
                {{ $group->documentos }} {{ $group->documentos === 1 ? $noun[0] : $noun[1] }}
            </span>
            @if ($group->abiertos > 0)
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ $group->abiertos }} {{ $openLabel }}</span>
            @endif
            @if ($group->url)
                <a href="{{ $group->url }}" class="fx-btn-link-primary text-xs">Abrir</a>
            @endif
        </div>
    </td>
    <td class="fx-td whitespace-nowrap py-2.5 text-right font-semibold text-slate-900">{{ Money::formatTotals($group->totals) }}</td>
    @if ($trailing > 0)
        <td class="fx-td" colspan="{{ $trailing }}"></td>
    @endif
</tr>
