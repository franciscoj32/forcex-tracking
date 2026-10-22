{{--
    Reporte de estado de una RO para enviar al cliente.
    Solo incluye informacion visible al cliente: fechas validadas por el Customer,
    hitos con texto estandar y notas del Customer. Nunca costos ni bitacora interna.
--}}
@php
    $pdfSettings = \App\Models\AppSetting::current();
    $brand = \App\Support\BrandPalette::isValidHex($pdfSettings->color_primario) ? $pdfSettings->color_primario : \App\Support\BrandPalette::DEFAULT_PRIMARY;
    $accent = \App\Support\BrandPalette::isValidHex($pdfSettings->color_acento) ? $pdfSettings->color_acento : \App\Support\BrandPalette::DEFAULT_ACCENT;
    $states = \App\Models\Shipment::TRACKING_STATES;
    $percent = (int) round(($progressIndex / (count($states) - 1)) * 100);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de carga {{ $shipment->routing_order }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10.5px; color: #0f172a; }
        h1 { font-size: 17px; margin: 0; }
        h2 { font-size: 12px; margin: 18px 0 6px; color: {{ $brand }}; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        .facts td { padding: 6px; border-bottom: 1px solid #e2e8f0; }
        .facts td.label { width: 32%; color: #64748b; }
        .progress { background: #e2e8f0; height: 8px; margin-top: 6px; border-radius: 4px; }
        .progress span { display: block; height: 8px; background: {{ $accent }}; border-radius: 4px; }
        .steps td { font-size: 7.5px; padding: 3px 2px; color: #94a3b8; text-align: center; }
        .steps td.done { color: #334155; }
        .steps td.current { color: {{ $brand }}; font-weight: bold; }
        .timeline td { padding: 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .note { color: #6d28d9; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 9px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 8px; }
    </style>
</head>
<body>
    @include('pdf._brand', ['pdfSubtitle' => 'Estado de tu carga'])

    <h1>Embarque {{ $shipment->nro_orden_trabajo }}</h1>
    <div class="muted">RO {{ $shipment->routing_order }} · Código de seguimiento {{ $shipment->tracking_code }}</div>

    <h2>Resumen</h2>
    <table class="facts">
        <tr><td class="label">Cliente</td><td>{{ $shipment->client->razon_social }}</td></tr>
        <tr><td class="label">Ruta</td><td>{{ $shipment->puerto_carga }} ({{ $shipment->origen }}) &rarr; {{ $shipment->puerto_descarga }} ({{ $shipment->destino_final }})</td></tr>
        <tr><td class="label">Tipo de carga · Incoterm</td><td>{{ $shipment->tipo_carga === 'AEREO' ? 'Aéreo' : $shipment->tipo_carga }} · {{ $shipment->incoterm ?: '—' }}</td></tr>
        <tr><td class="label">Salida estimada (ETD)</td><td>{{ optional($shipment->fecha_etd_cliente)->format('d/m/Y') ?? 'Por confirmar' }}</td></tr>
        <tr><td class="label">Llegada estimada (ETA)</td><td>{{ optional($shipment->fecha_eta_cliente)->format('d/m/Y') ?? 'Por confirmar' }}</td></tr>
        <tr><td class="label">Dónde está ahora</td><td>{{ $shipment->ubicacion_actual ?: 'Por actualizar' }}</td></tr>
        @if ($shipment->containers->isNotEmpty())
            <tr><td class="label">{{ $shipment->containers->count() === 1 ? 'Contenedor' : 'Contenedores' }}</td><td>{{ $shipment->containers->map(fn ($c) => $c->numero.' ('.$c->tipo.')')->join(', ') }}</td></tr>
        @endif
        @if ($shipment->bl)
            <tr><td class="label">BL / AWB</td><td>{{ $shipment->bl }}</td></tr>
        @endif
    </table>

    <h2>Avance · {{ $percent }}%</h2>
    <div><strong>{{ $shipment->estado_actual }}:</strong> {{ \App\Models\Shipment::clientStateText($shipment->estado_actual) }}</div>
    <div class="progress"><span style="width: {{ max($percent, 3) }}%"></span></div>
    <table class="steps">
        <tr>
            @foreach ($states as $index => $state)
                <td class="{{ $index < $progressIndex ? 'done' : ($index === $progressIndex ? 'current' : '') }}">{{ $index < $progressIndex ? '✓ ' : '' }}{{ $state }}</td>
            @endforeach
        </tr>
    </table>

    <h2>Novedades</h2>
    <table class="timeline">
        @forelse ($shipment->clientVisibleEvents->sortByDesc('fecha') as $event)
            <tr>
                <td style="width: 18%" class="muted">{{ $event->fecha->format('d/m/Y H:i') }}</td>
                <td style="width: 24%" class="{{ $event->isClientNote() ? 'note' : '' }}">{{ $event->isClientNote() ? 'Mensaje de tu Customer Service' : $event->estado }}</td>
                <td>{{ $event->clientText() }}</td>
            </tr>
        @empty
            <tr><td class="muted">Aún no hay novedades registradas.</td></tr>
        @endforelse
    </table>

    <div class="footer">
        Tu contacto: {{ $shipment->customerService?->name ?? 'por asignar' }}{{ $shipment->customerService ? ' · '.$shipment->customerService->email : '' }}.
        Sigue tu carga en línea: {{ route('tracking.public', ['code' => $shipment->tracking_code]) }}
    </div>
</body>
</html>
