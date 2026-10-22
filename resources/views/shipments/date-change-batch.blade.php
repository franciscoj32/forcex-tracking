{{-- Cambios de ETD/ETA hechos juntos (actualizacion masiva): el Customer los valida en grupo --}}
@php
    $first = $changes->first();
    $vessel = $first->shipment->nave ? trim($first->shipment->nave.($first->shipment->viaje ? ' · viaje '.$first->shipment->viaje : '')) : null;
    $statusLabel = fn ($change) => match ($change->estado) {
        \App\Models\ShipmentDateChange::STATUS_PENDING => ['Por validar', 'text-amber-700'],
        \App\Models\ShipmentDateChange::STATUS_APPROVED => ['Publicado'.($change->reviewedBy ? ' por '.$change->reviewedBy->name : ''), 'text-accent-700'],
        \App\Models\ShipmentDateChange::STATUS_DISCARDED => ['Descartado'.($change->reviewedBy ? ' por '.$change->reviewedBy->name : ''), 'text-slate-500'],
        default => ['Reemplazado por un cambio más reciente', 'text-slate-500'],
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1>Cambios de fecha en grupo</h1>
    </x-slot>

    <div class="space-y-4" x-data="{ selected: @js($reviewableIds) }">
        <section class="fx-card p-4">
            <p class="text-slate-900">
                <span class="font-semibold">{{ $changes->count() }} RO</span>{{ $vessel ? ' · '.$vessel : '' }}
            </p>
            <p class="text-sm text-slate-500">Informado por {{ $first->changedBy->name }} el {{ $first->created_at->format('d/m/Y H:i') }}. El cliente sigue viendo la fecha anterior hasta que se publique.</p>
        </section>

        @if ($result)
            <section class="fx-card p-4">
                @if ($result['done'] !== [])
                    <p class="text-sm text-slate-700"><span class="font-semibold text-accent-700">Revisados:</span> {{ implode(', ', $result['done']) }}</p>
                @endif
                @if ($result['skipped'] !== [])
                    <ul class="mt-2 space-y-0.5 text-sm text-slate-700">
                        @foreach ($result['skipped'] as [$ro, $reason])
                            <li><span class="font-medium text-amber-700">{{ $ro }}:</span> {{ $reason }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        <section class="fx-card">
            <div class="overflow-x-auto">
                <table class="fx-table">
                    <thead>
                        <tr>
                            <th class="fx-th w-10"><span class="sr-only">Elegir</span></th>
                            <th class="fx-th">RO</th>
                            <th class="fx-th">Cliente</th>
                            <th class="fx-th">Fecha</th>
                            <th class="fx-th">El cliente ve</th>
                            <th class="fx-th">Nueva</th>
                            <th class="fx-th">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($changes as $change)
                            @php
                                $delay = $change->delayInDays();
                                [$label, $color] = $statusLabel($change);
                                $canSelect = in_array($change->id, $reviewableIds, true);
                            @endphp
                            <tr>
                                <td class="fx-td">
                                    @if ($canSelect)
                                        <input type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600"
                                               value="{{ $change->id }}" x-model.number="selected" aria-label="Elegir {{ $change->shipment->routing_order }}">
                                    @endif
                                </td>
                                <td class="fx-td whitespace-nowrap">
                                    <a href="{{ route('embarques.show', $change->shipment) }}" class="font-medium text-slate-900 hover:underline">{{ $change->shipment->routing_order }}</a>
                                </td>
                                <td class="fx-td">{{ $change->shipment->client?->razon_social }}</td>
                                <td class="fx-td">{{ $change->fieldLabel() }}</td>
                                <td class="fx-td whitespace-nowrap tabular-nums">{{ $change->valor_publicado?->format('d/m/Y') ?? 'sin fecha' }}</td>
                                <td class="fx-td whitespace-nowrap tabular-nums">
                                    <span class="font-semibold">{{ $change->valor_nuevo?->format('d/m/Y') ?? 'por confirmar' }}</span>
                                    @if ($delay)
                                        <span @class(['text-xs', 'text-rose-700' => $delay > 0, 'text-slate-500' => $delay < 0])>({{ $delay > 0 ? '+' : '' }}{{ $delay }} d)</span>
                                    @endif
                                </td>
                                <td class="fx-td whitespace-nowrap text-sm {{ $color }}">{{ $label }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($reviewableIds !== [])
            <section class="fx-card">
                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Validar <span x-text="selected.length"></span> cambio(s)</h2>
                <form method="POST" action="{{ route('cambios-fecha.lote.review', $lote) }}" class="space-y-3 p-4">
                    @csrf
                    <template x-for="id in selected" :key="id"><input type="hidden" name="change_ids[]" :value="id"></template>
                    <div>
                        <label class="fx-label" for="nota_cliente">Nota para los clientes (opcional, va en cada correo)</label>
                        <textarea class="fx-textarea" id="nota_cliente" name="nota_cliente" rows="2" maxlength="1000"
                            placeholder="Ej. La naviera reprogramó el arribo por congestión en el puerto."></textarea>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" name="accion" value="aprobar" class="fx-btn-primary" :disabled="selected.length === 0">Publicar a los clientes</button>
                        <button type="submit" name="accion" value="descartar" class="fx-btn-secondary" :disabled="selected.length === 0"
                                onclick="return confirm('¿Descartar los cambios seleccionados? Los clientes seguirán viendo la fecha anterior.')">Descartar</button>
                    </div>
                    <p class="text-xs text-slate-500">Al publicar, cada cliente recibe el aviso de su RO por separado.</p>
                </form>
            </section>
        @elseif ($changes->contains(fn ($change) => $change->isPending()))
            <p class="text-sm text-slate-500">Estos cambios los valida el Customer asignado a cada RO.</p>
        @endif
    </div>
</x-app-layout>
