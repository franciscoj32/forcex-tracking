{{-- Tracking publico: lo que ve el cliente con su codigo. Solo datos visibles al cliente. --}}
@php
    $company = \App\Models\AppSetting::current()->company_name;
    // Solo hitos (con texto estandar) y notas del Customer. Nunca la bitacora interna.
    $timeline = $shipment?->clientVisibleEvents?->sortByDesc('fecha')->values();
    $latestEventId = $timeline?->first()?->id;
    $progressStates = \App\Models\Shipment::TRACKING_STATES;
    $progressIndex = $shipment ? $shipment->currentTrackingStateIndex() : 0;
    $progressPercent = $shipment ? (int) round(($progressIndex / (count($progressStates) - 1)) * 100) : 0;
    $stage = $shipment ? \App\Support\ChartPalette::stage($shipment->estado_actual) : null;
    $date = fn ($value) => $value?->format('d/m/Y') ?? 'Por confirmar';
    $cargoLabel = $shipment?->tipo_carga === 'AEREO' ? 'Aéreo' : $shipment?->tipo_carga;
@endphp

<x-public-tracking-layout>
    <div class="space-y-4">
        {{-- Busqueda --}}
        <section @class(['fx-card p-4 sm:p-5', 'sm:p-8' => ! $shipment])>
            @unless ($shipment)
                <h1 class="text-xl font-semibold">Seguimiento de carga</h1>
                <p class="mt-1 text-slate-600">Ingresa el código que te envió {{ $company }}.</p>
            @endunless

            <form method="GET" action="{{ route('tracking.public') }}" @class(['flex flex-col gap-2 sm:flex-row', 'mt-4' => ! $shipment])>
                <label for="code" class="sr-only">Código de seguimiento</label>
                <input id="code" name="code" value="{{ $code }}" placeholder="Ej. FX-AB12CD34" autocomplete="off" required
                       class="fx-input mt-0 font-mono uppercase sm:max-w-xs">
                <button type="submit" class="fx-btn-primary">Consultar</button>
            </form>

            @if ($searched && ! $shipment)
                <p class="mt-3 rounded-md border border-rose-300 bg-rose-50 px-3 py-2 text-rose-800" role="alert">
                    No encontramos una carga con ese código. Revisa que esté bien escrito.
                </p>
            @endif
        </section>

        @if ($shipment)
            {{-- Encabezado del embarque --}}
            <section class="fx-card p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-700" aria-hidden="true">
                            <i data-lucide="{{ $shipment->heroTransportIcon() }}" class="h-5 w-5"></i>
                        </span>
                        <div>
                            <h1 class="text-xl font-semibold">Embarque {{ $shipment->nro_orden_trabajo }}</h1>
                            <p class="text-slate-600">{{ $cargoLabel }} · código <span class="font-mono">{{ $shipment->tracking_code }}</span></p>
                        </div>
                    </div>
                    <p class="inline-flex items-center gap-2 rounded-md bg-slate-50 px-3 py-1.5 font-medium text-slate-900">
                        <span class="fx-status-dot {{ $stage['dot'] }}"></span>
                        {{ $shipment->estado_actual }}
                    </p>
                </div>

                {{-- Ruta --}}
                <div class="mt-5 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3">
                    <div class="min-w-0">
                        <p class="text-xs text-slate-500">Origen</p>
                        <p class="flex items-center gap-2 truncate font-medium text-slate-900">
                            @if ($shipment->originCountryCode())
                                <span class="fi fi-{{ $shipment->originCountryCode() }} shrink-0 rounded-sm" aria-hidden="true"></span>
                            @endif
                            <span class="truncate">{{ $shipment->puerto_carga ?: $shipment->origen }}</span>
                        </p>
                        <p class="truncate text-xs text-slate-500">{{ $shipment->origen }}</p>
                    </div>
                    <div class="flex items-center gap-2 text-brand-600" aria-hidden="true">
                        <span class="h-px w-6 bg-slate-300 sm:w-16"></span>
                        <i data-lucide="{{ $shipment->routeTransportIcon() }}" class="h-5 w-5"></i>
                        <span class="h-px w-6 bg-slate-300 sm:w-16"></span>
                    </div>
                    <div class="min-w-0 text-right">
                        <p class="text-xs text-slate-500">Destino</p>
                        <p class="flex items-center justify-end gap-2 truncate font-medium text-slate-900">
                            @if ($shipment->destinationCountryCode())
                                <span class="fi fi-{{ $shipment->destinationCountryCode() }} shrink-0 rounded-sm" aria-hidden="true"></span>
                            @endif
                            <span class="truncate">{{ $shipment->puerto_descarga ?: $shipment->destino_final }}</span>
                        </p>
                        <p class="truncate text-xs text-slate-500">{{ $shipment->destino_final }}</p>
                    </div>
                </div>

                {{-- Fechas publicadas (validadas por el Customer) --}}
                <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-slate-500">Salida estimada (ETD)</dt>
                        <dd class="mt-0.5 font-medium tabular-nums text-slate-900">{{ $date($shipment->fecha_etd_cliente) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Llegada estimada (ETA)</dt>
                        <dd class="mt-0.5 font-medium tabular-nums text-slate-900">{{ $date($shipment->fecha_eta_cliente) }}</dd>
                    </div>
                    @if ($shipment->containers->isNotEmpty())
                        <div class="col-span-2">
                            <dt class="text-xs text-slate-500">{{ $shipment->containers->count() === 1 ? 'Contenedor' : 'Contenedores' }}</dt>
                            <dd class="mt-0.5 font-mono text-slate-900">{{ $shipment->containers->pluck('numero')->join(', ') }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- Avance --}}
            <section class="fx-card p-4 sm:p-5" aria-label="Avance de la carga">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="fx-section-title">Avance</h2>
                    <span class="text-sm font-medium tabular-nums text-slate-600">{{ $progressPercent }}%</span>
                </div>
                <p class="mt-1 text-slate-700">{{ \App\Models\Shipment::clientStateText($shipment->estado_actual) }}</p>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressPercent }}">
                    <div class="h-full rounded-full bg-accent-600" style="width: {{ max($progressPercent, 3) }}%"></div>
                </div>
                <ol class="mt-4 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($progressStates as $index => $state)
                        <li @class([
                            'flex items-center gap-2',
                            'text-slate-700' => $index < $progressIndex,
                            'font-semibold text-slate-900' => $index === $progressIndex,
                            'text-slate-400' => $index > $progressIndex,
                        ])>
                            <span @class([
                                'flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[10px] leading-none',
                                'bg-accent-600 text-white' => $index < $progressIndex,
                                'bg-brand-600 ring-4 ring-brand-100' => $index === $progressIndex,
                                'border border-slate-300' => $index > $progressIndex,
                            ])>@if ($index < $progressIndex)✓@endif</span>
                            {{ $state }}
                        </li>
                    @endforeach
                </ol>
            </section>

            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_18rem]">
                {{-- Linea de tiempo --}}
                <section class="fx-card">
                    <h2 class="fx-section-title border-b border-slate-200 px-4 py-3 sm:px-5">Novedades</h2>
                    <ol class="px-4 py-3 sm:px-5">
                        @forelse ($timeline ?? collect() as $event)
                            @php $isLatest = $event->id === $latestEventId; @endphp
                            <li class="relative flex gap-3 pb-4 last:pb-0">
                                @unless ($loop->last)
                                    <span class="absolute left-[5px] top-4 h-full w-px bg-slate-200"></span>
                                @endunless
                                <span @class([
                                    'relative mt-1.5 h-[11px] w-[11px] shrink-0 rounded-full',
                                    'bg-brand-600 ring-4 ring-brand-100' => $isLatest,
                                    'bg-accent-600' => ! $isLatest && ! $event->isClientNote(),
                                    'bg-violet-500' => ! $isLatest && $event->isClientNote(),
                                ])></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                        <p @class(['text-slate-900', 'font-semibold' => $isLatest, 'font-medium' => ! $isLatest])>
                                            {{ $event->isClientNote() ? 'Mensaje de tu Customer Service' : $event->estado }}
                                        </p>
                                        <time class="text-xs tabular-nums text-slate-500">{{ $event->fecha->format('d/m/Y H:i') }}</time>
                                    </div>
                                    <p class="mt-0.5 whitespace-pre-line text-slate-700">{{ $event->clientText() }}</p>
                                </div>
                            </li>
                        @empty
                            <li class="text-slate-500">Aún no hay novedades de esta carga.</li>
                        @endforelse
                    </ol>
                </section>

                <div class="space-y-4">
                    {{-- Donde esta ahora --}}
                    <section class="fx-card p-4">
                        <h2 class="flex items-center gap-2 text-sm font-medium text-slate-500">
                            <i data-lucide="map-pinned" class="h-4 w-4" aria-hidden="true"></i>
                            Dónde está ahora
                        </h2>
                        <p class="mt-1 font-medium text-slate-900">{{ $shipment->ubicacion_actual ?: 'Ubicación por actualizar' }}</p>
                        @if ($shipment->ubicacion_actualizada_en)
                            <p class="text-xs text-slate-500">Actualizado el {{ $shipment->ubicacion_actualizada_en->format('d/m/Y H:i') }}</p>
                        @endif
                        @if ($shipment->locationMapUrl())
                            <a class="fx-btn-link-primary mt-2 inline-block" href="{{ $shipment->locationMapUrl() }}" target="_blank" rel="noopener noreferrer">Ver en el mapa</a>
                        @endif
                    </section>

                    {{-- Contacto --}}
                    @if ($shipment->customerService)
                        <section class="fx-card p-4">
                            <h2 class="text-sm font-medium text-slate-500">Tu contacto</h2>
                            <p class="mt-1 font-medium text-slate-900">{{ $shipment->customerService->name }}</p>
                            <p class="text-xs text-slate-500">Customer Service</p>
                            @if ($shipment->customerService->email)
                                <a href="mailto:{{ $shipment->customerService->email }}?subject={{ rawurlencode($shipment->routing_order.' | Consulta') }}" class="fx-btn-secondary mt-3 w-full">Escribir un correo</a>
                            @endif
                        </section>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-public-tracking-layout>
