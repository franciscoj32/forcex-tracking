{{-- Resumen del embarque. Lo que ya esta en el encabezado no se repite aqui. --}}
@php
    $dash = '—';
    $date = fn ($value, string $format = 'd/m/Y') => $value?->format($format) ?? $dash;
    $responsibles = $shipment->userAssignments->where('assignment_type', \App\Models\ShipmentUserAssignment::TYPE_RESPONSABLE)->pluck('user.name')->filter();
    $followers = $shipment->userAssignments->where('assignment_type', \App\Models\ShipmentUserAssignment::TYPE_SEGUIMIENTO)->pluck('user.name')->filter();
    $publishedDiffers = fn ($internal, $published) => optional($internal)->toDateString() !== optional($published)->toDateString();
    $row = 'flex items-baseline justify-between gap-4 py-1.5';
    $dt = 'shrink-0 text-slate-500';
    $dd = 'min-w-0 text-right text-slate-900 break-words';
    // Cuenta regresiva de cada plazo (viene de show.blade.php, mismo calculo que las alertas).
    $deadline = fn (string $type) => $shipmentAlerts->first(fn ($alert) => $alert->type === $type && $alert->dueAt !== null);
@endphp

<div class="space-y-4">
    @include('shipments.partials.date-changes')

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="fx-card">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Carga y ruta</h2>
            <dl class="divide-y divide-slate-100 px-4 py-1">
                <div class="{{ $row }}"><dt class="{{ $dt }}">Incoterm</dt><dd class="{{ $dd }}">{{ $shipment->incoterm ?: $dash }}</dd></div>
                <div class="{{ $row }}"><dt class="{{ $dt }}">País origen → llegada</dt><dd class="{{ $dd }}">{{ $shipment->origen }} → {{ $shipment->destino_final }}</dd></div>
                <div class="{{ $row }}"><dt class="{{ $dt }}">{{ $shipment->isAirCargo() ? 'Aerolínea' : 'Naviera' }}</dt><dd class="{{ $dd }}">{{ $shipment->naviera ?: $dash }}</dd></div>
                <div class="{{ $row }}">
                    <dt class="{{ $dt }}">{{ $shipment->isAirCargo() ? 'Vuelo · viaje' : 'Nave · viaje' }}</dt>
                    <dd class="{{ $dd }}">
                        @if ($shipment->nave)
                            <a href="{{ route('embarques.masivo', ['nave' => $shipment->nave, 'viaje' => $shipment->viaje]) }}" class="hover:underline" title="Ver las RO de este buque">{{ $shipment->nave }}</a>
                            · {{ $shipment->viaje ?: $dash }}
                        @else
                            {{ $dash }}
                        @endif
                    </dd>
                </div>
                <div class="{{ $row }}"><dt class="{{ $dt }}">Agente de origen</dt><dd class="{{ $dd }}">{{ $shipment->agente_origen ?: $dash }}</dd></div>
                <div class="{{ $row }}"><dt class="{{ $dt }}">Consignatario</dt><dd class="{{ $dd }}">{{ $shipment->consignatario ?: $dash }}</dd></div>
                <div class="{{ $row }}">
                    <dt class="{{ $dt }}">Peso · volumen</dt>
                    <dd class="{{ $dd }} tabular-nums">
                        {{ $shipment->peso ? $shipment->weightInTons() : $dash }} · {{ $shipment->volumen ? number_format($shipment->volumen, 2).' CBM' : $dash }}
                    </dd>
                </div>
                <div class="{{ $row }}">
                    <dt class="{{ $dt }}">Dónde está</dt>
                    <dd class="{{ $dd }}">
                        {{ $shipment->ubicacion_actual ?: $dash }}
                        @if ($shipment->ubicacion_actual && $shipment->locationMapUrl())
                            <a href="{{ $shipment->locationMapUrl() }}" target="_blank" rel="noopener noreferrer" class="fx-btn-link-primary ml-1">mapa</a>
                        @endif
                        @if ($shipment->ubicacion_actualizada_en)
                            <span class="block text-xs text-slate-500">{{ $shipment->ubicacion_actualizada_en->format('d/m H:i') }}</span>
                        @endif
                    </dd>
                </div>
                <div class="{{ $row }}">
                    <dt class="{{ $dt }}">Crédito</dt>
                    <dd class="{{ $dd }}">{{ $shipment->creditLabel() }}@if ($shipment->credito_forzado) <span class="text-xs text-amber-700">(forzado por Admin)</span>@endif</dd>
                </div>
            </dl>
        </section>

        <section class="fx-card scroll-mt-20" id="plazos">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Fechas y almacén</h2>
            <dl class="divide-y divide-slate-100 px-4 py-1 tabular-nums">
                <div class="{{ $row }}">
                    <dt class="{{ $dt }}">Cut-off</dt>
                    <dd class="{{ $dd }}">
                        {{ $date($shipment->fecha_cut_off, 'd/m/Y H:i') }}
                        @include('alerts.countdown', ['alert' => $deadline(\App\Services\Alerts\Alert::CUT_OFF)])
                        @if ($shipment->cut_off_cumplido_en)
                            <span class="block text-xs font-medium text-accent-700">
                                Cumplido · {{ $shipment->cut_off_cumplido_en->format('d/m H:i') }}{{ $shipment->cutOffConfirmedBy ? ' · '.$shipment->cutOffConfirmedBy->name : '' }}
                            </span>
                            @can('confirmCutOff', $shipment)
                                <form method="POST" action="{{ route('embarques.cut-off.destroy', $shipment) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-slate-500 hover:text-slate-900 hover:underline">Desmarcar</button>
                                </form>
                            @endcan
                        @else
                            @can('confirmCutOff', $shipment)
                                <form method="POST" action="{{ route('embarques.cut-off.store', $shipment) }}" class="mt-0.5">
                                    @csrf
                                    <button type="submit" class="fx-btn-link-primary text-xs">Marcar cumplido</button>
                                </form>
                            @endcan
                        @endif
                    </dd>
                </div>
                @if ($shipment->fecha_eta_original && $publishedDiffers($shipment->fecha_eta_original, $shipment->fecha_eta))
                    <div class="{{ $row }}"><dt class="{{ $dt }}">ETA original</dt><dd class="{{ $dd }}">{{ $date($shipment->fecha_eta_original) }}</dd></div>
                @endif
                <div class="{{ $row }}">
                    <dt class="{{ $dt }}">ETD que ve el cliente</dt>
                    <dd @class([$dd, 'font-medium text-amber-700' => $publishedDiffers($shipment->fecha_etd, $shipment->fecha_etd_cliente)])>{{ $date($shipment->fecha_etd_cliente) }}</dd>
                </div>
                <div class="{{ $row }}">
                    <dt class="{{ $dt }}">ETA que ve el cliente</dt>
                    <dd @class([$dd, 'font-medium text-amber-700' => $publishedDiffers($shipment->fecha_eta, $shipment->fecha_eta_cliente)])>{{ $date($shipment->fecha_eta_cliente) }}</dd>
                </div>
                <div class="{{ $row }}"><dt class="{{ $dt }}">Descarga</dt><dd class="{{ $dd }}">{{ $date($shipment->fecha_descarga) }}</dd></div>
                @if ($shipment->tipo_carga === 'FCL')
                    <div class="{{ $row }}">
                        <dt class="{{ $dt }}">Días libres demurrage · detention</dt>
                        <dd class="{{ $dd }}">
                            {{ $shipment->dias_libres_demurrage ?? $dash }} · {{ $shipment->dias_libres_detention ?? $dash }}
                            @include('alerts.countdown', ['alert' => $deadline(\App\Services\Alerts\Alert::DEMURRAGE), 'prefix' => 'Demurrage'])
                            @include('alerts.countdown', ['alert' => $deadline(\App\Services\Alerts\Alert::DETENTION), 'prefix' => 'Detention'])
                        </dd>
                    </div>
                @endif
                <div class="{{ $row }}"><dt class="{{ $dt }}">Almacén</dt><dd class="{{ $dd }}">{{ $shipment->almacen_direccionamiento ?: $dash }}</dd></div>
                <div class="{{ $row }}"><dt class="{{ $dt }}">Tarja</dt><dd class="{{ $dd }}">{{ $shipment->fecha_tarja ? $shipment->fecha_tarja->format('d/m/Y H:i') : 'Pendiente' }}</dd></div>
                <div class="{{ $row }}"><dt class="{{ $dt }}">Ingreso a almacén</dt><dd class="{{ $dd }}">{{ $date($shipment->fecha_ingreso_almacen, 'd/m/Y H:i') }}</dd></div>
                <div class="{{ $row }}">
                    <dt class="{{ $dt }}">Días libres de almacén</dt>
                    <dd class="{{ $dd }}">
                        {{ $shipment->dias_libres_almacen ?? $dash }}
                        @include('alerts.countdown', ['alert' => $deadline(\App\Services\Alerts\Alert::STORAGE)])
                    </dd>
                </div>
                @if ($shipment->hasTarja())
                    <div class="{{ $row }}">
                        <dt class="{{ $dt }}">Bultos · peso tarjado</dt>
                        <dd class="{{ $dd }}">{{ $shipment->bultos_tarjados ?? $dash }} · {{ $shipment->peso_tarjado_kg !== null ? \App\Models\Shipment::formatTons($shipment->peso_tarjado_kg) : $dash }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="fx-card">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Responsables y endoso</h2>
            <dl class="divide-y divide-slate-100 px-4 py-1">
                <div class="{{ $row }}"><dt class="{{ $dt }}">Comercial</dt><dd class="{{ $dd }}">{{ $shipment->comercial?->name ?? 'Venta directa' }}</dd></div>
                <div class="{{ $row }}"><dt class="{{ $dt }}">Customer Service</dt><dd class="{{ $dd }}">{{ $shipment->customerService?->name ?? $dash }}</dd></div>
                <div class="{{ $row }}"><dt class="{{ $dt }}">Operador</dt><dd class="{{ $dd }}">{{ $shipment->operador?->name ?? 'Sin asignar' }}</dd></div>
                @if ($responsibles->isNotEmpty() || $followers->isNotEmpty())
                    <div class="{{ $row }}"><dt class="{{ $dt }}">Reciben alertas</dt><dd class="{{ $dd }}">{{ $responsibles->merge($followers)->unique()->join(', ') }}</dd></div>
                @endif
                <div class="{{ $row }}"><dt class="{{ $dt }}">Endoso</dt><dd class="{{ $dd }}">{{ ucfirst(str_replace('_', ' ', (string) $shipment->estado_endoso)) }}</dd></div>
                @if ($shipment->fecha_endoso || $shipment->endorsementResponsible)
                    <div class="{{ $row }}">
                        <dt class="{{ $dt }}">Fecha · responsable endoso</dt>
                        <dd class="{{ $dd }}">{{ $date($shipment->fecha_endoso) }} · {{ $shipment->endorsementResponsible?->name ?? $dash }}</dd>
                    </div>
                @endif
            </dl>
        </section>
    </div>

    @unless ($shipment->isAirCargo())
        @include('shipments.partials.containers')
    @endunless

    @php
        $notes = array_filter([
            'Observaciones internas' => $shipment->observaciones,
            'Observación de tarja' => $shipment->observacion_tarja,
            'Observación de endoso' => $shipment->observacion_endoso,
        ]);
    @endphp
    @if ($notes !== [])
        <section class="fx-card divide-y divide-slate-100">
            @foreach ($notes as $label => $text)
                <div class="px-4 py-3">
                    <h2 class="text-sm font-medium text-slate-500">{{ $label }}</h2>
                    <p class="mt-1 whitespace-pre-line text-slate-800">{{ $text }}</p>
                </div>
            @endforeach
        </section>
    @endif
</div>
