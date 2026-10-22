<x-app-layout>
    <x-slot name="header">
        <h1>Mis cargas</h1>
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <section class="fx-card">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Cargas activas <span class="font-normal text-slate-500">({{ $activeShipments->count() }})</span></h2>
            <ul class="divide-y divide-slate-100">
                @forelse ($activeShipments as $shipment)
                    <li>
                        <a href="{{ route('embarques.show', $shipment) }}" class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-slate-50">
                            <span>
                                <span class="font-semibold text-slate-900">{{ $shipment->nro_orden_trabajo }}</span>
                                <span class="block text-sm text-slate-500">{{ $shipment->puerto_carga }} → {{ $shipment->puerto_descarga }}</span>
                            </span>
                            <span class="text-right text-sm">
                                <span class="block text-slate-800">{{ $shipment->estado_actual }}</span>
                                <span class="text-slate-500">ETA {{ $shipment->fecha_eta_cliente?->format('d/m/Y') ?? '—' }}</span>
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-4 text-slate-500">Sin cargas activas.</li>
                @endforelse
            </ul>
        </section>

        <section class="fx-card">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Últimos documentos</h2>
            <ul class="divide-y divide-slate-100">
                @forelse ($recentDocuments as $document)
                    <li class="flex items-center justify-between gap-4 px-4 py-3">
                        <span>
                            <span class="text-slate-900">{{ $document->tipo_documento }}</span>
                            <span class="block text-sm text-slate-500">{{ $document->shipment->nro_orden_trabajo }}</span>
                        </span>
                        <a href="{{ route('documentos.download', $document) }}" class="fx-btn-link-primary">Descargar</a>
                    </li>
                @empty
                    <li class="px-4 py-4 text-slate-500">Sin documentos.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-app-layout>
