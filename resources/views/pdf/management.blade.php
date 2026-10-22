@use('App\Support\Money')
@php
    $pdfSettings = \App\Models\AppSetting::current();
    $brand = \App\Support\BrandPalette::isValidHex($pdfSettings->color_primario) ? $pdfSettings->color_primario : \App\Support\BrandPalette::DEFAULT_PRIMARY;
    $accent = \App\Support\BrandPalette::isValidHex($pdfSettings->color_acento) ? $pdfSettings->color_acento : \App\Support\BrandPalette::DEFAULT_ACCENT;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen gerencial {{ $period['label'] }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #0f172a; margin: 0; }
        h1 { font-size: 17px; margin: 0; }
        h2 { font-size: 12px; margin: 16px 0 6px; color: {{ $brand }}; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        .kpis td { width: 14.28%; border: 1px solid #e2e8f0; padding: 8px; vertical-align: top; }
        .kpis .label { font-size: 8.5px; color: #64748b; }
        .kpis .value { font-size: 15px; font-weight: bold; margin-top: 4px; }
        .grid th { background: #f1f5f9; color: #334155; text-align: left; padding: 5px; font-size: 9px; border-bottom: 2px solid {{ $brand }}; }
        .grid td { border-bottom: 1px solid #e2e8f0; padding: 5px; }
        .right { text-align: right; }
        .bar { background: #e2e8f0; height: 6px; }
        .bar span { display: block; height: 6px; background: {{ $accent }}; }
        .positive { color: #047857; }
        .negative { color: #b91c1c; }
        .late { color: #b91c1c; font-weight: bold; }
        .two-cols td.col { width: 50%; vertical-align: top; padding-right: 10px; }
        .footer { margin-top: 18px; font-size: 8px; color: #94a3b8; }
    </style>
</head>
<body>
    @include('pdf._brand', ['pdfSubtitle' => 'Resumen gerencial'])

    <h1>Resumen gerencial · {{ $period['label'] }}</h1>
    <div class="muted">Cada embarque cuenta en el mes de su ETD; si aún no tiene ETD, en el de su registro.</div>

    <h2>Números del mes</h2>
    <table class="kpis">
        <tr>
            <td><div class="label">Embarques</div><div class="value">{{ $counts['total'] }}</div></td>
            <td><div class="label">FCL</div><div class="value">{{ $counts['FCL'] }}</div></td>
            <td><div class="label">LCL</div><div class="value">{{ $counts['LCL'] }}</div></td>
            <td><div class="label">Aéreo</div><div class="value">{{ $counts['AEREO'] }}</div></td>
            <td><div class="label">Venta</div><div class="value">{{ Money::format($totals['sales']) }}</div></td>
            <td><div class="label">Costo</div><div class="value">{{ Money::format($totals['costs']) }}</div></td>
            <td>
                <div class="label">Margen comercial</div>
                <div class="value {{ $totals['margin'] >= 0 ? 'positive' : 'negative' }}">{{ Money::format($totals['margin']) }}</div>
                <div class="muted">{{ $totals['margin_percent'] !== null ? $totals['margin_percent'].'% sobre la venta' : '-' }}</div>
            </td>
        </tr>
    </table>
    @if ($totals['incomplete'] > 0)
        <p class="muted">{{ $totals['incomplete'] }} RO con montos en PEN sin tipo de cambio no se incluyen en los totales.</p>
    @endif

    <table class="two-cols">
        <tr>
            <td class="col">
                <h2>Ranking de comerciales por margen</h2>
                <table class="grid">
                    <thead>
                        <tr><th>#</th><th>Comercial</th><th class="right">RO</th><th class="right">Venta</th><th class="right">Margen</th><th class="right">%</th><th style="width: 22%"></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($ranking as $row)
                            <tr>
                                <td>{{ $row['position'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td class="right">{{ $row['shipments'] }}</td>
                                <td class="right">{{ Money::format($row['sales']) }}</td>
                                <td class="right {{ $row['margin'] >= 0 ? 'positive' : 'negative' }}">{{ Money::format($row['margin']) }}</td>
                                <td class="right">{{ $row['margin_percent'] ?? '-' }}</td>
                                <td><div class="bar"><span style="width: {{ $row['bar'] }}%"></span></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">Sin embarques en el periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td class="col">
                <h2>Margen de los últimos 6 meses</h2>
                <table class="grid">
                    <thead><tr><th>Mes</th><th class="right">Embarques</th><th class="right">Margen USD</th></tr></thead>
                    <tbody>
                        @foreach ($trend as $month)
                            <tr>
                                <td>{{ $month['label'] }} {{ substr($month['key'], 0, 4) }}</td>
                                <td class="right">{{ $month['shipments'] }}</td>
                                <td class="right">{{ Money::format($month['margin']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2>Embarques retrasados (ETA vencida sin arribo) · {{ $delayed->count() }}</h2>
    <table class="grid">
        <thead>
            <tr><th>RO</th><th>Cliente</th><th>ETA</th><th class="right">Días de retraso</th><th>Estado</th><th>Customer</th><th>Operador</th></tr>
        </thead>
        <tbody>
            @forelse ($delayed as $shipment)
                <tr>
                    <td>{{ $shipment->routing_order }}</td>
                    <td>{{ $shipment->client->razon_social }}</td>
                    <td>{{ $shipment->fecha_eta->format('d/m/Y') }}</td>
                    <td class="right late">{{ $shipment->daysLate() }}</td>
                    <td>{{ $shipment->estado_actual }}</td>
                    <td>{{ $shipment->customerService?->name ?? '-' }}</td>
                    <td>{{ $shipment->operador?->name ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No hay embarques retrasados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detalle de embarques del mes</h2>
    <table class="grid">
        <thead>
            <tr><th>RO</th><th>Cliente</th><th>Tipo</th><th>Comercial</th><th>ETD</th><th>Estado</th><th class="right">Venta</th><th class="right">Costo</th><th class="right">Margen</th></tr>
        </thead>
        <tbody>
            @forelse ($shipments as $shipment)
                @php $financials = $shipment->financials(); @endphp
                <tr>
                    <td>{{ $shipment->routing_order }}</td>
                    <td>{{ $shipment->client->razon_social }}</td>
                    <td>{{ $shipment->tipo_carga }}</td>
                    <td>{{ $shipment->comercial?->name ?? 'Venta directa' }}</td>
                    <td>{{ optional($shipment->fecha_etd)->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $shipment->estado_actual }}</td>
                    <td class="right">{{ Money::format($financials->salesUsd) }}</td>
                    <td class="right">{{ Money::format($financials->costsUsd) }}</td>
                    <td class="right">{{ $financials->marginUsd() === null ? 'Sin TC' : Money::format($financials->marginUsd()) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Sin embarques en el periodo.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Margen (USD) = Tarifas de venta (USD) - Costos de proveedores (USD). Los montos en PEN se convierten con el tipo de cambio congelado de cada RO.</div>
</body>
</html>
