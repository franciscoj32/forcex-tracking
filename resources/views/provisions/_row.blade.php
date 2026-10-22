{{--
    Fila de una provision. Requiere $provision y $canManage.
    $grouping: 'proveedor' | 'ro' | 'ninguno'. Dentro de un grupo no se repite el dato
    del grupo y la fila va con sangria. $rowAttributes: Alpine del grupo (opcional).
--}}
@use('App\Support\Money')
@php
    $grouping ??= 'ninguno';
    $nested = $grouping !== 'ninguno';
@endphp
<tr @class(['hover:bg-slate-50', 'bg-white' => $nested]) {!! $rowAttributes ?? '' !!}>
    <td @class(['fx-td', 'pl-11' => $nested])>
        @if ($grouping === 'ro')
            <p class="text-slate-900">{{ $provision->supplier?->razon_social ?? 'Sin proveedor' }}</p>
        @else
            <a href="{{ route('embarques.show', ['shipment' => $provision->shipment_id, 'tab' => 'finanzas']) }}" class="font-medium text-slate-900 hover:underline">{{ $provision->shipment?->nro_orden_trabajo }}</a>
            <p class="max-w-[14rem] truncate text-xs text-slate-500">{{ $provision->shipment?->client?->razon_social }}</p>
        @endif
    </td>
    <td class="fx-td">
        @if ($grouping === 'ninguno')
            <p class="text-slate-900">{{ $provision->supplier?->razon_social ?? 'Sin proveedor' }}</p>
            <p class="text-xs text-slate-500">{{ $provision->concept?->nombre ?? $provision->descripcion }}</p>
        @else
            <p class="text-slate-700">{{ $provision->concept?->nombre ?? $provision->descripcion ?? '—' }}</p>
        @endif
    </td>
    <td class="fx-td whitespace-nowrap">{{ $provision->nro_factura_proveedor ?: '—' }}</td>
    <td class="fx-td whitespace-nowrap">{{ $provision->fecha->format('d/m/Y') }}</td>
    <td class="fx-td whitespace-nowrap">
        @if ($provision->isPaid())
            <p class="text-slate-700">Pagado {{ $provision->fecha_pago?->format('d/m/Y') }}</p>
            <p class="text-xs text-slate-500">Op. {{ $provision->nro_operacion_bancaria ?: '—' }}</p>
        @else
            <span class="fx-badge bg-amber-100 text-amber-800">Por pagar</span>
        @endif
    </td>
    <td class="fx-td whitespace-nowrap text-right text-slate-900">{{ Money::format((float) $provision->monto, $provision->moneda ?: 'USD') }}</td>
    @if ($canManage)
        <td class="fx-td text-right"><a href="{{ route('provisiones.edit', $provision) }}" class="fx-btn-link-primary">Editar</a></td>
    @endif
</tr>
