{{--
    Marca de la empresa para los PDF (dompdf no lee las variables CSS del sistema).
    Define $pdfBrand, $pdfAccent y $pdfLogo (data URI o null) e imprime el encabezado.
--}}
@php
    $pdfSettings = \App\Models\AppSetting::current();
    $pdfBrand = \App\Support\BrandPalette::isValidHex($pdfSettings->color_primario) ? $pdfSettings->color_primario : \App\Support\BrandPalette::DEFAULT_PRIMARY;
    $pdfLogo = null;
    $pdfLogoHeight = 34;
    if (filled($pdfSettings->logo_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($pdfSettings->logo_path)) {
        $logoMime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($pdfSettings->logo_path) ?: 'image/png';
        // dompdf no dibuja SVG de forma fiable: en ese caso se usa la sigla.
        if ($logoMime !== 'image/svg+xml') {
            $logoBytes = \Illuminate\Support\Facades\Storage::disk('public')->get($pdfSettings->logo_path);
            // Un emblema (logo cuadrado o redondo) necesita mas alto para leerse.
            $pdfLogoHeight = $pdfSettings->logoIsEmblem() ? 58 : 34;

            // dompdf guarda la imagen sin comprimir: un logo grande hace pesado el PDF.
            // Se reduce a 4 veces el alto mostrado (nitido al imprimir) sobre fondo blanco.
            $logoImage = function_exists('imagecreatefromstring') ? @imagecreatefromstring($logoBytes) : false;
            if ($logoImage !== false && imagesy($logoImage) > $pdfLogoHeight * 4) {
                $targetHeight = $pdfLogoHeight * 4;
                $targetWidth = (int) round(imagesx($logoImage) * $targetHeight / imagesy($logoImage));
                $resized = imagecreatetruecolor($targetWidth, $targetHeight);
                imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
                imagecopyresampled($resized, $logoImage, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($logoImage), imagesy($logoImage));
                ob_start();
                imagejpeg($resized, null, 88);
                $logoBytes = ob_get_clean();
                $logoMime = 'image/jpeg';
            }

            $pdfLogo = 'data:'.$logoMime.';base64,'.base64_encode($logoBytes);
        }
    }
@endphp
<table style="width:100%; border-collapse:collapse; margin-bottom:10px;">
    <tr>
        <td style="width:60%; vertical-align:middle;">
            @if ($pdfLogo)
                <img src="{{ $pdfLogo }}" style="height:{{ $pdfLogoHeight }}px;" alt="{{ $pdfSettings->company_name }}">
            @else
                <span style="display:inline-block; background:{{ $pdfBrand }}; color:#fff; font-weight:bold; padding:6px 8px; border-radius:4px;">{{ mb_substr($pdfSettings->brand_abbr, 0, 3) }}</span>
                <span style="font-size:13px; font-weight:bold; margin-left:6px;">{{ $pdfSettings->company_name }}</span>
            @endif
        </td>
        <td style="width:40%; text-align:right; vertical-align:middle; color:#64748b; font-size:9px;">
            {{ $pdfSubtitle ?? '' }}<br>Generado el {{ now()->format('d/m/Y H:i') }}
        </td>
    </tr>
</table>
<div style="height:3px; background:{{ $pdfBrand }}; margin-bottom:12px;"></div>
