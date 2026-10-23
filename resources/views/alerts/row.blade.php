{{--
    Una alerta critica. Requiere $alert (App\Services\Alerts\Alert) y $now.
    $actions: mostrar Vista / Posponer / Abrir (Inicio).
    $compact: version de la ficha, alineada con las tareas de "Requiere atencion"
              (sin barra lateral: la tarjeta ya tiene el borde de color).
--}}
@use('App\Services\Alerts\Alert')
@use('App\Services\Alerts\AlertLevel')
@php
    $actions ??= false;
    $compact ??= false;
    $shipment = $alert->shipment;
    $canConfirmCutOff = $alert->type === Alert::CUT_OFF && auth()->user()->can('confirmCutOff', $shipment);
    // La pre-alerta de quien puede enviarla no pasa por aqui: tiene su propio aviso
    // (alerts.pre-alert-trigger). Aqui solo la ve, informativa, el Operador.
@endphp

@if ($compact)
    <li class="flex flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
        <div class="flex min-w-0 items-start gap-2">
            <x-nav-icon :name="$alert->icon()" class="mt-0.5 h-4 w-4 {{ AlertLevel::style($alert->level, 'text') }}" />
            <div class="min-w-0 text-slate-700">
                <span class="font-semibold text-slate-900">{{ $alert->typeLabel() }}:</span>
                {{ $alert->detail() }}
                @if ($canConfirmCutOff)
                    <form method="POST" action="{{ route('embarques.cut-off.store', $shipment) }}" class="mt-0.5">
                        @csrf
                        <button type="submit" class="fx-btn-link-primary">Marcar cut-off cumplido</button>
                    </form>
                @endif
            </div>
        </div>
        <p class="shrink-0 pl-6 text-sm tabular-nums sm:pl-0 sm:text-right">
            <span class="font-bold {{ AlertLevel::style($alert->level, 'text') }}">{{ $alert->countdown($now) }}</span>
            <span class="text-slate-500"> · {{ $alert->subLabel($now) }}</span>
        </p>
    </li>
@else
    <li class="relative flex flex-col gap-3 py-3 pl-5 pr-4 sm:flex-row sm:items-center sm:gap-4">
        <span class="absolute inset-y-0 left-0 w-1 {{ AlertLevel::style($alert->level, 'bar') }}" aria-hidden="true"></span>

        <div class="flex min-w-0 flex-1 items-start gap-3">
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ AlertLevel::style($alert->level, 'soft') }} {{ AlertLevel::style($alert->level, 'text') }}">
                <x-nav-icon :name="$alert->icon()" class="h-4 w-4" />
            </span>
            <div class="min-w-0">
                <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="font-semibold text-slate-900">{{ $alert->typeLabel() }}</span>
                    <a href="{{ route('embarques.show', $shipment) }}" class="truncate text-slate-500 hover:text-slate-900 hover:underline">
                        {{ $shipment->routing_order }}{{ $shipment->client ? ' · '.$shipment->client->razon_social : '' }}
                    </a>
                    @if ($alert->escalated && auth()->user()->isAdmin())
                        <span class="rounded bg-rose-50 px-1.5 py-0.5 text-xs font-medium text-rose-700">Sin atender hace {{ $alert->criticalFor($now) }}</span>
                    @endif
                </p>
                <p class="mt-0.5 text-sm text-slate-600">{{ $alert->detail() }}</p>
                @if ($canConfirmCutOff && $actions)
                    <form method="POST" action="{{ route('embarques.cut-off.store', $shipment) }}" class="mt-0.5">
                        @csrf
                        <input type="hidden" name="volver" value="inicio">
                        <button type="submit" class="fx-btn-link-primary text-sm">Marcar cut-off cumplido</button>
                    </form>
                @endif
                @if ($alert->status === Alert::STATUS_SEEN && $alert->seenBy)
                    <p class="mt-0.5 text-xs text-slate-500">Vista por {{ $alert->seenBy->name }} · {{ $alert->seenAt?->format('d/m H:i') }}</p>
                @elseif ($alert->status === Alert::STATUS_SNOOZED)
                    <p class="mt-0.5 text-xs text-slate-500">Pospuesta hasta el {{ $alert->snoozedUntil?->format('d/m H:i') }}</p>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 pl-11 sm:flex-nowrap sm:justify-end sm:gap-4 sm:pl-0">
            <div class="text-left tabular-nums sm:text-right">
                <p class="text-sm font-bold {{ AlertLevel::style($alert->level, 'text') }}">{{ $alert->countdown($now) }}</p>
                <p class="text-xs text-slate-500">{{ $alert->subLabel($now) }}</p>
            </div>

            @if ($actions)
                <div class="flex items-center gap-2">
                    @if ($alert->status === Alert::STATUS_ACTIVE)
                        <form method="POST" action="{{ route('alertas.store') }}">
                            @csrf
                            <input type="hidden" name="shipment_id" value="{{ $shipment->id }}">
                            <input type="hidden" name="tipo" value="{{ $alert->type }}">
                            <input type="hidden" name="huella" value="{{ $alert->fingerprint }}">
                            <input type="hidden" name="accion" value="vista">
                            <button type="submit" class="fx-btn-secondary h-8 px-3 text-xs">Vista</button>
                        </form>
                        <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                            <button type="button" class="fx-btn-secondary h-8 px-3 text-xs" x-on:click="open = ! open" :aria-expanded="open">Posponer ▾</button>
                            <div x-show="open" x-cloak x-on:click.outside="open = false"
                                 class="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                                @foreach (['4h' => '4 horas', 'manana' => 'Hasta mañana 8:00'] as $option => $optionLabel)
                                    @continue($option === 'manana' && ! $alert->dueAt?->isAfter($now))
                                    <form method="POST" action="{{ route('alertas.store') }}">
                                        @csrf
                                        <input type="hidden" name="shipment_id" value="{{ $shipment->id }}">
                                        <input type="hidden" name="tipo" value="{{ $alert->type }}">
                                        <input type="hidden" name="huella" value="{{ $alert->fingerprint }}">
                                        <input type="hidden" name="accion" value="pospuesta">
                                        <input type="hidden" name="opcion" value="{{ $option }}">
                                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">{{ $optionLabel }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @elseif ($alert->stateId)
                        <form method="POST" action="{{ route('alertas.destroy', $alert->stateId) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="fx-btn-secondary h-8 px-3 text-xs">Reactivar</button>
                        </form>
                    @endif
                    <a href="{{ route('embarques.show', $shipment) }}" class="fx-btn-primary h-8 px-3 text-xs">Abrir</a>
                </div>
            @endif
        </div>
    </li>
@endif
