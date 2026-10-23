{{--
    Cotizacion para el cliente: logo y datos de la empresa (Configuracion),
    cliente, servicio, tarifas con IGV por moneda, condiciones y cuentas bancarias.
--}}
@php
    $pdfSettings = \App\Models\AppSetting::current();
    $brand = \App\Support\BrandPalette::isValidHex($pdfSettings->color_primario) ? $pdfSettings->color_primario : \App\Support\BrandPalette::DEFAULT_PRIMARY;
    $air = $quotation->tipo_carga === 'AEREO';
    $service = array_filter([
        'Tipo de carga' => $quotation->cargoLabel(),
        'Incoterm' => $quotation->incoterm,
        'Origen' => $quotation->origen,
        'Destino' => $quotation->destino,
        $air ? 'Aeropuerto de salida' : 'Puerto de carga' => $quotation->puerto_carga,
        $air ? 'Aeropuerto de llegada' : 'Puerto de descarga' => $quotation->puerto_descarga,
        'Mercancía' => $quotation->mercancia,
        'Contenedores' => $quotation->contenedores,
        'Peso' => $quotation->peso !== null ? number_format((float) $quotation->peso, 2).' kg' : null,
        'Volumen' => $quotation->volumen !== null ? rtrim(rtrim(number_format((float) $quotation->volumen, 3), '0'), '.').' m³' : null,
        $air ? 'Aerolínea' : 'Naviera' => $quotation->naviera,
        'Tiempo de tránsito' => $quotation->tiempo_transito,
        'Frecuencia' => $quotation->frecuencia,
        'Días libres' => $quotation->dias_libres !== null ? $quotation->dias_libres.' días' : null,
    ], fn ($value) => filled($value));
    $serviceRows = array_chunk($service, 2, true);
    $companyLine = $pdfSettings->companyDocumentLine();
    $banks = collect(preg_split('/\r\n|\r|\n/', (string) $pdfSettings->cuentas_bancarias))->map(fn ($line) => trim($line))->filter()->values();
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $quotation->numero }}</title>
    <style>
        @page { margin: 28px 32px 40px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9.5px; color: #0f172a; }
        h1 { font-size: 16px; margin: 0; color: {{ $brand }}; }
        h2 { font-size: 10.5px; margin: 14px 0 5px; color: {{ $brand }}; text-transform: uppercase; letter-spacing: .3px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        .company { font-size: 8.5px; color: #475569; margin: -6px 0 10px; }
        .box td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .box td.label { width: 17%; color: #64748b; }
        .items th { background: {{ $brand }}; color: #fff; font-weight: bold; padding: 5px 6px; text-align: left; font-size: 8.5px; }
        .items td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .totals td { padding: 2px 6px; }
        .totals .grand td { font-weight: bold; border-top: 1px solid #cbd5e1; padding-top: 4px; }
        .conditions p { margin: 0 0 4px; line-height: 1.35; }
        .signature { margin-top: 26px; width: 45%; border-top: 1px solid #94a3b8; padding-top: 4px; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('pdf._brand', ['pdfSubtitle' => 'Cotización '.$quotation->numero])

    @if ($companyLine !== '')
        <div class="company">{{ $pdfSettings->company_name }} · {{ $companyLine }}</div>
    @endif

    <table>
        <tr>
            <td style="vertical-align: bottom;">
                <h1>COTIZACIÓN N.° {{ $quotation->numero }}</h1>
            </td>
            <td style="text-align: right; vertical-align: bottom;" class="muted">
                Fecha: <strong style="color:#0f172a">{{ $quotation->fecha->format('d/m/Y') }}</strong>
                &nbsp;·&nbsp; Válida hasta: <strong style="color:#0f172a">{{ $quotation->validUntil()->format('d/m/Y') }}</strong>
            </td>
        </tr>
    </table>

    <h2>Cliente</h2>
    <table class="box">
        <tr>
            <td class="label">Señores</td><td>{{ $quotation->razon_social }}</td>
            <td class="label">RUC</td><td style="width: 20%">{{ $quotation->ruc }}</td>
        </tr>
        @if ($quotation->direccion)
            <tr><td class="label">Dirección</td><td colspan="3">{{ $quotation->direccion }}</td></tr>
        @endif
        @if ($quotation->contacto_nombre || $quotation->contacto_correo || $quotation->contacto_telefono)
            <tr>
                <td class="label">Atención</td><td>{{ $quotation->contacto_nombre ?: '—' }}</td>
                <td class="label">Contacto</td><td>{{ collect([$quotation->contacto_correo, $quotation->contacto_telefono])->filter()->join(' · ') ?: '—' }}</td>
            </tr>
        @endif
    </table>

    <h2>Servicio</h2>
    <table class="box">
        @foreach ($serviceRows as $pair)
            <tr>
                @foreach ($pair as $label => $value)
                    <td class="label">{{ $label }}</td><td style="width: 33%">{{ $value }}</td>
                @endforeach
                @if (count($pair) === 1)
                    <td class="label"></td><td></td>
                @endif
            </tr>
        @endforeach
    </table>

    <h2>Tarifas</h2>
    <table class="items">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="num" style="width: 9%">Cant.</th>
                <th class="num" style="width: 15%">Precio unit.</th>
                <th class="center" style="width: 10%">IGV</th>
                <th class="num" style="width: 16%">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $item)
                <tr>
                    <td>{{ $item->descripcion }}</td>
                    <td class="num">{{ $quantity($item->cantidad) }}</td>
                    <td class="num">{{ \App\Support\Money::format((float) $item->precio_unitario, $item->moneda) }}</td>
                    <td class="center">{{ $item->afecto_igv ? 'Afecto' : 'Inafecto' }}</td>
                    <td class="num">{{ \App\Support\Money::format($item->total(), $item->moneda) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 6px;">
        <tr>
            <td style="width: 50%;"></td>
            @foreach ($totals as $currency => $amounts)
                <td style="vertical-align: top; padding-left: 10px;">
                    <table class="totals">
                        <tr><td class="muted">Subtotal {{ $currency }}</td><td class="num">{{ \App\Support\Money::format($amounts['subtotal'], $currency) }}</td></tr>
                        <tr><td class="muted">IGV {{ $igvPercent }}%</td><td class="num">{{ \App\Support\Money::format($amounts['igv'], $currency) }}</td></tr>
                        <tr class="grand"><td>Total {{ $currency }}</td><td class="num">{{ \App\Support\Money::format($amounts['total'], $currency) }}</td></tr>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>

    @if (filled($quotation->observaciones))
        <h2>Observaciones</h2>
        <div class="conditions"><p>{!! nl2br(e($quotation->observaciones)) !!}</p></div>
    @endif

    @if (filled($quotation->condiciones))
        <h2>Condiciones</h2>
        <div class="conditions">
            @foreach (preg_split('/\r\n\r\n|\n\n|\r\r/', trim($quotation->condiciones)) as $paragraph)
                <p>{!! nl2br(e(trim($paragraph))) !!}</p>
            @endforeach
        </div>
    @endif

    @if ($banks->isNotEmpty())
        <h2>Cuentas bancarias</h2>
        <div class="conditions">
            <p class="muted" style="margin-bottom: 3px;">A nombre de {{ $pdfSettings->company_name }}{{ filled($pdfSettings->empresa_ruc) ? ' · RUC '.$pdfSettings->empresa_ruc : '' }}</p>
            @foreach ($banks as $bank)
                <p>{{ $bank }}</p>
            @endforeach
        </div>
    @endif

    <div class="signature">
        <strong>{{ $quotation->comercial?->name }}</strong><br>
        <span class="muted">Ejecutivo comercial · {{ $pdfSettings->company_name }}</span>
        @if ($quotation->comercial?->email)
            <br><span class="muted">{{ $quotation->comercial->email }}</span>
        @endif
    </div>

    <div class="footer">{{ $pdfSettings->company_name }} · Cotización {{ $quotation->numero }}</div>
</body>
</html>
