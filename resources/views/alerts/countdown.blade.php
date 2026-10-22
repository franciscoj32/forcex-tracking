{{-- Cuenta regresiva compacta de un plazo (ficha). $alert puede ser null; $prefix opcional. --}}
@if ($alert)
    <span class="block text-xs font-medium {{ \App\Services\Alerts\AlertLevel::style($alert->level, 'text') }}">
        {{ ($prefix ?? null) ? $prefix.': ' : '' }}{{ $alert->countdown($now) }} · {{ $alert->subLabel($now) }}
    </span>
@endif
