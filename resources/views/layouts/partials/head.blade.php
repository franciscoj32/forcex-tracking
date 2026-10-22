{{-- Cabecera comun: fuente precargada, colores de la empresa y favicon. --}}
@php
    $headSettings = \App\Models\AppSetting::current();
    $fontPreload = null;
    try {
        $fontPreload = \Illuminate\Support\Facades\Vite::asset('resources/fonts/ibm-plex-sans-latin-wght-normal.woff2');
    } catch (\Throwable) {
        $fontPreload = null;
    }
    $faviconSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="12" fill="'
        .e($headSettings->color_primario ?: \App\Support\BrandPalette::DEFAULT_PRIMARY)
        .'"/><text x="32" y="41" font-family="Arial,sans-serif" font-size="'.(mb_strlen((string) $headSettings->brand_abbr) > 2 ? 20 : 26)
        .'" font-weight="700" fill="#fff" text-anchor="middle">'.e(mb_substr((string) $headSettings->brand_abbr, 0, 3)).'</text></svg>';
    $faviconUrl = $headSettings->logoUrl() ?: 'data:image/svg+xml,'.rawurlencode($faviconSvg);
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $headSettings->color_primario ?: \App\Support\BrandPalette::DEFAULT_PRIMARY }}">
<title>{{ isset($pageTitle) && $pageTitle ? $pageTitle.' · ' : '' }}{{ $headSettings->program_name }}</title>
<link rel="icon" href="{{ $faviconUrl }}">
@if ($fontPreload)
    <link rel="preload" href="{{ $fontPreload }}" as="font" type="font/woff2" crossorigin>
@endif
<style>{!! $headSettings->brandCss() !!}</style>
@vite(['resources/css/app.css', 'resources/js/app.js'])
