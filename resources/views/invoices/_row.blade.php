{{--
    Fila de una factura. Requiere $invoice y $canManage.
    $grouping: 'cliente' | 'ro' | 'ninguno'. Dentro de un grupo no se repite el dato
    del grupo y la fila va con sangria. $rowAttributes: Alpine del grupo (opcional).
--}}
@use('App\Support\Money')
@php
    $grouping ??= 'ninguno';
    $nested = $grouping !== 'ninguno';
@endphp
<tr @class(['hover:bg-slate-50', 'bg-white' => $nested]) {!! $rowAttributes ?? '' !!}>
    <td @class(['fx-td whitespace-nowrap font-medium text-slate-900', 'pl-11' => $nested])>{{ $invoice->numero }}</td>
    @if ($grouping !== 'ro')
        <td class="fx-td">
            <a href="{{ route('embarques.show', ['shipment' => $invoice->shipment_id, 'tab' => 'finanzas']) }}" class="text-slate-900 hover:underline">{{ $invoice->shipment?->nro_orden_trabajo }}</a>
            @if ($grouping === 'ninguno')
                <p class="max-w-[16rem] truncate text-xs text-slate-500">{{ $invoice->shipment?->client?->razon_social }}</p>
            @else
                <p class="text-xs text-slate-500">{{ $invoice->shipment?->routing_order }}</p>
            @endif
        </td>
    @endif
    <td class="fx-td whitespace-nowrap">{{ $invoice->created_at->format('d/m/Y') }}</td>
    <td class="fx-td whitespace-nowrap">
        @if ($invoice->estado === 'pagada')
            <span class="text-slate-700">Pagada</span>
        @else
            <span class="fx-badge bg-amber-100 text-amber-800">{{ $invoice->estado === 'emitida' ? 'Emitida, por cobrar' : 'Pendiente de emitir' }}</span>
        @endif
    </td>
    <td class="fx-td whitespace-nowrap text-right text-slate-900">{{ Money::format((float) $invoice->monto, $invoice->moneda ?: 'USD') }}</td>
    @if ($canManage)
        <td class="fx-td text-right"><a href="{{ route('facturacion.edit', $invoice) }}" class="fx-btn-link-primary">Editar</a></td>
    @endif
</tr>
