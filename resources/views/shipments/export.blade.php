<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de embarques</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #0f172a; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        .meta { margin-bottom: 18px; }
        .meta p { margin: 4px 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; }
        th { background: #e2e8f0; text-align: left; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Reporte de embarques FORCEX</h1>
    <div class="meta">
        <p><strong>Generado:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</p>
        @foreach ($filters as $label => $value)
            <p><strong>{{ $label }}:</strong> {{ $value ?: 'Todos' }}</p>
        @endforeach
        <p><strong>Total:</strong> {{ $shipments->count() }} embarque(s)</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>OT</th>
                <th>Routing Order</th>
                <th>BL</th>
                <th>Cliente</th>
                <th>Tipo</th>
                <th>Origen</th>
                <th>Puerto descarga</th>
                <th>Destino final</th>
                <th>ETD</th>
                <th>ETA</th>
                <th>Estado actual</th>
                <th>Estado endoso</th>
                <th>Responsable interno</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($shipments as $shipment)
                <tr>
                    <td>{{ $shipment->nro_orden_trabajo }}</td>
                    <td>{{ $shipment->routing_order }}</td>
                    <td>{{ $shipment->bl }}</td>
                    <td>{{ $shipment->client->razon_social }}</td>
                    <td>{{ $shipment->tipo_carga }}</td>
                    <td>{{ $shipment->origen }}</td>
                    <td>{{ $shipment->puerto_descarga }}</td>
                    <td>{{ $shipment->destino_final }}</td>
                    <td>{{ optional($shipment->fecha_etd)->format('d/m/Y') }}</td>
                    <td>{{ optional($shipment->fecha_eta)->format('d/m/Y') }}</td>
                    <td>{{ $shipment->estado_actual }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $shipment->estado_endoso)) }}</td>
                    <td>{{ $shipment->endorsementResponsible?->name ?: 'Sin asignar' }}</td>
                    <td>{{ $shipment->observaciones ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
