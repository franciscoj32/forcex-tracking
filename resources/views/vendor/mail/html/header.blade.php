@props(['url'])
@php
    $mailSettings = \App\Models\AppSetting::current();
    $mailBrand = \App\Support\BrandPalette::isValidHex($mailSettings->color_primario) ? $mailSettings->color_primario : \App\Support\BrandPalette::DEFAULT_PRIMARY;
    $mailLogo = $mailSettings->logoUrl();
@endphp
<tr>
<td class="header" style="border-bottom: 3px solid {{ $mailBrand }};">
<a href="{{ $url }}" style="display: inline-block; color: #0f172a; font-size: 18px; font-weight: bold; text-decoration: none;">
@if ($mailLogo)
<img src="{{ $mailLogo }}" alt="{{ $mailSettings->company_name }}" style="max-height: {{ $mailSettings->logoIsEmblem() ? 64 : 44 }}px; max-width: 220px;">
@else
<span style="display: inline-block; background: {{ $mailBrand }}; color: #ffffff; border-radius: 4px; padding: 4px 8px; font-size: 13px; margin-right: 6px;">{{ mb_substr($mailSettings->brand_abbr, 0, 3) }}</span>{!! $slot !!}
@endif
</a>
</td>
</tr>
