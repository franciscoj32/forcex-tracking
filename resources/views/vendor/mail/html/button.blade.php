@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    $mailSettings = \App\Models\AppSetting::current();
    $mailBrand = \App\Support\BrandPalette::isValidHex($mailSettings->color_primario) ? $mailSettings->color_primario : \App\Support\BrandPalette::DEFAULT_PRIMARY;
    $mailText = 'rgb('.str_replace(' ', ',', \App\Support\BrandPalette::contrastText($mailBrand, \App\Support\BrandPalette::DEFAULT_PRIMARY)).')';
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
{{-- Color de la empresa en linea: los clientes de correo ignoran las variables CSS. --}}
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener"
   style="background-color: {{ $mailBrand }}; border-bottom: 8px solid {{ $mailBrand }}; border-left: 18px solid {{ $mailBrand }}; border-right: 18px solid {{ $mailBrand }}; border-top: 8px solid {{ $mailBrand }}; color: {{ $mailText }}; border-radius: 4px;">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
