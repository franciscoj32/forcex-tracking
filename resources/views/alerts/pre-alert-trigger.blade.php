{{--
    Aviso "Pre-alerta de llegada pendiente": un boton bien visible que abre la
    ventana con el formulario. Sigue visible hasta que se envie (si se retiene,
    queda como "retenida" para no olvidarla).

    $alert   App\Services\Alerts\Alert de tipo pre_alerta
    $now     CarbonImmutable
    $opens   'dialog' (ficha: ventana ya cargada) o 'remote' (Inicio: se carga al hacer clic)
    $withShipment  mostrar RO y cliente (Inicio)
--}}
@use('App\Services\Alerts\AlertLevel')
@php
    $shipment = $alert->shipment;
    $danger = $alert->level === AlertLevel::DANGER;
    $held = filled($alert->data['retenida'] ?? null);
    $resend = filled($alert->data['enviada_eta'] ?? null);
    $title = match (true) {
        $held => 'Pre-alerta retenida: pendiente de envío',
        $resend => 'Pre-alerta por reenviar: cambió la ETA',
        default => 'Pre-alerta de llegada pendiente',
    };
    $detail = collect([
        ($withShipment ?? false) ? $shipment->routing_order.($shipment->client ? ' · '.$shipment->client->razon_social : '') : null,
        $alert->countdown($now).' ('.$alert->subLabel($now).')',
        $held ? 'Motivo: '.$alert->data['retenida'] : null,
    ])->filter()->join(' · ');
    $click = ($opens ?? 'dialog') === 'remote'
        ? "\$dispatch('open-pre-alert', { url: ".\Illuminate\Support\Js::from(route('embarques.pre-alerta', ['shipment' => $shipment, 'modal' => 1, 'volver' => 'inicio'])).", title: ".\Illuminate\Support\Js::from('Pre-alerta de llegada · '.$shipment->routing_order).' })'
        : "\$dispatch('open-dialog', 'pre-alerta')";
@endphp
<button type="button" x-data x-on:click="{{ $click }}"
    @class([
        'group flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2',
        'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-600' => $danger,
        'bg-amber-400 text-amber-950 hover:bg-amber-300 focus:ring-amber-500' => ! $danger,
    ])>
    <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/25">
        <x-nav-icon name="send" class="h-4 w-4" />
        @unless ($held)
            <span class="absolute -right-0.5 -top-0.5 flex h-3 w-3">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                <span class="relative inline-flex h-3 w-3 rounded-full bg-white"></span>
            </span>
        @endunless
    </span>
    <span class="min-w-0 flex-1">
        <span class="block font-semibold">{{ $title }}</span>
        <span @class(['block truncate text-sm', 'text-rose-50' => $danger, 'text-amber-900' => ! $danger])>{{ $detail }}</span>
    </span>
    <span @class([
        'hidden shrink-0 rounded px-2.5 py-1 text-xs font-semibold sm:inline-block',
        'bg-white text-rose-700' => $danger,
        'bg-amber-950 text-amber-50' => ! $danger,
    ])>Revisar y enviar</span>
</button>
