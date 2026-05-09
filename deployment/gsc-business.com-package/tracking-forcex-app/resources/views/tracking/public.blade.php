@php
    $timeline = $shipment?->events?->sortByDesc('fecha')->values();
    $latestEventId = $timeline?->first()?->id;
@endphp

<x-public-tracking-layout>
    <style>
        .tracking-page {
            max-width: 980px;
            margin: 0 auto;
            padding: 24px;
            color: #071526;
        }

        .lucide-icon {
            width: 20px;
            height: 20px;
            stroke-width: 2.1;
        }

        .tracking-search-card {
            border: 1px solid #dbe6f1;
            border-radius: 24px;
            background: #f8fbff;
            padding: 24px;
        }

        .tracking-search-card label {
            display: block;
            margin-bottom: 10px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #0f4c81;
        }

        .tracking-search-row {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .tracking-search-row input {
            flex: 1;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            padding: 14px 16px;
            font-size: 15px;
            color: #0f172a;
            background: #ffffff;
        }

        .tracking-search-row input:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.12);
        }

        .tracking-search-row button {
            border: 0;
            border-radius: 16px;
            padding: 14px 20px;
            background: linear-gradient(135deg, #0f5bd7 0%, #2563eb 100%);
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.22);
        }

        .tracking-search-row button:disabled,
        .tracking-search-row input:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .shipment-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 22px;
            padding: 28px 32px;
            border-radius: 28px;
            background: linear-gradient(135deg, #08233d 0%, #0b5f79 55%, #0b8194 100%);
            color: #ffffff;
            box-shadow: 0 18px 35px rgba(4, 26, 46, 0.25);
        }

        .hero-left {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .hero-icon {
            width: 58px;
            height: 58px;
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 22px;
            display: grid;
            place-items: center;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.08));
            color: #ffffff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15);
        }

        .hero-icon .lucide-icon {
            width: 30px;
            height: 30px;
        }

        .eyebrow {
            font-size: 12px;
            letter-spacing: 5px;
            font-weight: 800;
            color: #bff7ff;
            text-transform: uppercase;
        }

        .shipment-hero h1 {
            margin: 6px 0 8px;
            font-size: 34px;
            line-height: 1;
        }

        .shipment-hero p {
            margin: 0;
            color: #e7fbff;
            font-size: 15px;
        }

        .status-text {
            color: #9ff7c7;
            font-weight: 800;
        }

        .route-card {
            min-width: 210px;
            padding: 18px 22px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.75);
            background: rgba(255, 255, 255, 0.1);
        }

        .route-card span {
            display: block;
            font-size: 12px;
            letter-spacing: 4px;
            text-transform: uppercase;
            font-weight: 800;
            color: #d8fbff;
            margin-bottom: 12px;
        }

        .route-mini {
            display: flex;
            gap: 12px;
            align-items: center;
            text-transform: capitalize;
            font-weight: 700;
        }

        .route-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .country-flag {
            width: 22px;
            height: 16px;
            border-radius: 3px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            flex-shrink: 0;
        }

        .route-mini-arrow {
            color: #9fe8ff;
            font-size: 20px;
            line-height: 1;
        }

        .route-visual {
            display: grid;
            grid-template-columns: 1fr 1.3fr 1fr 180px;
            gap: 18px;
            align-items: center;
            margin: 28px 0;
        }

        .route-point,
        .eta-box {
            border: 1px solid #dbe6f1;
            border-radius: 22px;
            padding: 20px 22px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .route-point small,
        .eta-box span {
            display: block;
            color: #64748b;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .route-point strong,
        .eta-box strong {
            font-size: 19px;
            text-transform: capitalize;
            color: #0f172a;
        }

        .point-icon,
        .eta-icon,
        .route-state-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .point-icon,
        .eta-icon {
            margin-right: 8px;
            vertical-align: middle;
        }

        .point-icon {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            margin-bottom: 10px;
        }

        .point-icon.origin-icon {
            background: linear-gradient(180deg, #ffe4ef, #ffd0e3);
            color: #db2777;
        }

        .point-icon.destination-icon {
            background: linear-gradient(180deg, #f3f4f6, #e5e7eb);
            color: #111827;
        }

        .eta-icon {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: linear-gradient(180deg, #f3e8ff, #e9d5ff);
            color: #7c3aed;
            margin-right: 10px;
        }

        .route-line {
            position: relative;
            height: 3px;
            background: linear-gradient(90deg, #0ea5e9, #14b8a6);
            border-radius: 999px;
        }

        .route-line::before,
        .route-line::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 13px;
            height: 13px;
            border-radius: 999px;
            transform: translateY(-50%);
        }

        .route-line::before {
            left: 0;
            background: #0ea5e9;
        }

        .route-line::after {
            right: 0;
            background: #14b8a6;
        }

        .route-state-icon {
            position: absolute;
            top: -30px;
            left: 48%;
            transform: translateX(-50%);
            width: 58px;
            height: 58px;
            border-radius: 999px;
            background: #ffffff;
            color: #1d4ed8;
            box-shadow: 0 16px 26px rgba(14, 165, 233, 0.18);
        }

        .route-state-icon .lucide-icon {
            width: 30px;
            height: 30px;
        }

        .timeline-card {
            border: 1px solid #dbe6f1;
            border-radius: 28px;
            padding: 32px;
            background: #ffffff;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.06);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            margin-bottom: 26px;
        }

        .timeline-header h2 {
            margin: 0 0 8px;
            font-size: 22px;
        }

        .timeline-header p {
            margin: 0;
            color: #64748b;
        }

        .events-badge {
            background: #cffafe;
            color: #075985;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .timeline {
            position: relative;
        }

        .timeline::before {
            content: "";
            position: absolute;
            left: 18px;
            top: 12px;
            bottom: 12px;
            width: 2px;
            background: #dbeafe;
        }

        .timeline-item {
            position: relative;
            display: grid;
            grid-template-columns: 38px 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-dot {
            position: relative;
            z-index: 2;
            width: 38px;
            height: 38px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            margin-top: 22px;
            color: #ffffff;
        }

        .timeline-dot .lucide-icon {
            width: 18px;
            height: 18px;
        }

        .timeline-item.current .timeline-dot {
            background: #2563eb;
            box-shadow: 0 0 0 8px rgba(37, 99, 235, 0.12);
        }

        .timeline-item.completed .timeline-dot {
            background: #059669;
            box-shadow: 0 0 0 8px rgba(5, 150, 105, 0.10);
        }

        .timeline-content {
            border-radius: 22px;
            padding: 20px 22px;
            border: 1px solid #dbeafe;
            background: #f8fbff;
        }

        .timeline-item.current .timeline-content {
            background: linear-gradient(135deg, #dffbff, #c8f4f7);
            border-color: #99f6e4;
        }

        .timeline-item.completed .timeline-content {
            background: #f0fdf7;
            border-color: #86efac;
        }

        .timeline-top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
        }

        .timeline-top h3 {
            margin: 10px 0 0;
            font-size: 18px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .timeline-top time {
            font-size: 15px;
            font-weight: 800;
            color: #065f46;
            white-space: nowrap;
        }

        .timeline-item.current time {
            color: #0f172a;
        }

        .timeline-content p {
            margin: 12px 0 0;
            color: #475569;
            font-size: 15px;
        }

        .event-heading-icon {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: #dbeafe;
            color: #2563eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .timeline-item.completed .event-heading-icon {
            background: #dcfce7;
            color: #059669;
        }

        .badge-current,
        .badge-completed {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 13px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
            color: #ffffff;
        }

        .badge-current {
            background: #2563eb;
        }

        .badge-completed {
            background: #059669;
        }

        .tracking-alert {
            border-radius: 20px;
            padding: 16px 18px;
            font-size: 14px;
        }

        .tracking-alert-warning {
            border: 1px solid #fcd34d;
            background: #fffbeb;
            color: #92400e;
        }

        .tracking-alert-error {
            border: 1px solid #fda4af;
            background: #fff1f2;
            color: #be123c;
        }

        @media (max-width: 768px) {
            .tracking-search-row,
            .shipment-hero,
            .timeline-top,
            .timeline-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .shipment-hero {
                padding: 24px;
            }

            .shipment-hero h1 {
                font-size: 28px;
            }

            .route-card {
                width: 100%;
            }

            .route-visual {
                grid-template-columns: 1fr;
            }

            .route-line {
                height: 44px;
                width: 3px;
                margin-left: 22px;
            }

            .route-line::before,
            .route-line::after {
                left: 50%;
                transform: translate(-50%, -50%);
            }

            .route-state-icon {
                top: 8px;
                left: 34px;
            }

            .timeline-card {
                padding: 22px;
            }

            .timeline-item {
                grid-template-columns: 30px 1fr;
                gap: 14px;
            }

            .tracking-page {
                padding: 18px;
            }
        }
    </style>

    <section class="tracking-page space-y-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-cyan-700">Consulta publica</p>
            <h1 class="mt-2 text-3xl font-semibold text-slate-900">Seguimiento de carga</h1>
            <p class="mt-3 text-sm text-slate-600">
                Ingresa el codigo de seguimiento compartido por FORCEX LOGISTICS para consultar el estado actual de tu embarque.
            </p>
        </div>

        @if (! $trackingAvailable)
            <div class="tracking-alert tracking-alert-warning">
                La consulta publica por codigo de seguimiento aun no esta disponible en este entorno.
            </div>
        @endif

        <form method="GET" action="{{ route('tracking.public') }}" class="tracking-search-card">
            <label for="code">Codigo de seguimiento</label>
            <div class="tracking-search-row">
                <input
                    id="code"
                    name="code"
                    value="{{ $code }}"
                    placeholder="Ejemplo: FX-AB12CD34"
                    autocomplete="off"
                    required
                    @disabled(! $trackingAvailable)
                >
                <button type="submit" @disabled(! $trackingAvailable)>Consultar carga</button>
            </div>
        </form>

        @if ($trackingAvailable && $searched && ! $shipment)
            <div class="tracking-alert tracking-alert-error">
                No encontramos una carga asociada a ese codigo. Verifica el dato e intentalo nuevamente.
            </div>
        @endif

        @if ($trackingAvailable && $shipment)
            <div>
                <section class="shipment-hero">
                    <div class="hero-left">
                        <div class="hero-icon" aria-hidden="true">
                            <i data-lucide="{{ $shipment->heroTransportIcon() }}" class="lucide-icon"></i>
                        </div>
                        <div>
                            <span class="eyebrow">FORCEX LOGISTICS</span>
                            <h1>Embarque {{ $shipment->nro_orden_trabajo }}</h1>
                            <p>
                                Codigo: <strong>{{ $shipment->tracking_code }}</strong> · Estado actual:
                                <span class="status-text">{{ $shipment->estado_actual }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="route-card">
                        <span>Ruta actual</span>
                        <div class="route-mini">
                            <strong class="route-label">
                                @if ($shipment->originCountryCode())
                                    <span class="fi fi-{{ $shipment->originCountryCode() }} country-flag" aria-hidden="true"></span>
                                @endif
                                <span>{{ $shipment->origen }}</span>
                            </strong>
                            <span class="route-mini-arrow">→</span>
                            <strong class="route-label">
                                @if ($shipment->destinationCountryCode())
                                    <span class="fi fi-{{ $shipment->destinationCountryCode() }} country-flag" aria-hidden="true"></span>
                                @endif
                                <span>{{ $shipment->destino_final }}</span>
                            </strong>
                        </div>
                    </div>
                </section>

                <section class="route-visual">
                    <div class="route-point">
                        <span class="point-icon origin-icon" aria-hidden="true">
                            <i data-lucide="map-pinned" class="lucide-icon"></i>
                        </span>
                        <small>Origen</small>
                        <strong class="route-label">
                            @if ($shipment->originCountryCode())
                                <span class="fi fi-{{ $shipment->originCountryCode() }} country-flag" aria-hidden="true"></span>
                            @endif
                            <span>{{ $shipment->origen }}</span>
                        </strong>
                    </div>

                    <div class="route-line">
                        <span class="route-state-icon" aria-hidden="true">
                            <i data-lucide="{{ $shipment->routeTransportIcon() }}" class="lucide-icon"></i>
                        </span>
                    </div>

                    <div class="route-point">
                        <span class="point-icon destination-icon" aria-hidden="true">
                            <i data-lucide="flag-triangle-right" class="lucide-icon"></i>
                        </span>
                        <small>Destino final</small>
                        <strong class="route-label">
                            @if ($shipment->destinationCountryCode())
                                <span class="fi fi-{{ $shipment->destinationCountryCode() }} country-flag" aria-hidden="true"></span>
                            @endif
                            <span>{{ $shipment->destino_final }}</span>
                        </strong>
                    </div>

                    <div class="eta-box">
                        <span>
                            <span class="eta-icon" aria-hidden="true">
                                <i data-lucide="clock-3" class="lucide-icon"></i>
                            </span>
                            ETA
                        </span>
                        <strong>{{ optional($shipment->fecha_eta)->format('d/m/Y') ?: 'Sin fecha registrada' }}</strong>
                    </div>
                </section>

                <section class="timeline-card">
                    <div class="timeline-header">
                        <div>
                            <h2>Linea de tiempo</h2>
                            <p>Consulta cronologica del avance operativo registrado.</p>
                        </div>
                        <span class="events-badge">{{ $timeline?->count() ?? 0 }} evento(s)</span>
                    </div>

                    <div class="timeline">
                        @forelse ($timeline ?? collect() as $event)
                            @php
                                $isLatest = $event->id === $latestEventId;
                            @endphp
                            <div class="timeline-item {{ $isLatest ? 'current' : 'completed' }}">
                                <div class="timeline-dot" aria-hidden="true">
                                    <i data-lucide="{{ $isLatest ? 'arrow-right' : 'badge-check' }}" class="lucide-icon"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-top">
                                        <div>
                                            <span class="{{ $isLatest ? 'badge-current' : 'badge-completed' }}">
                                                {{ $isLatest ? '→ Actual' : '✓ Completado' }}
                                            </span>
                                            <h3>
                                                <span class="event-heading-icon" aria-hidden="true">
                                                    <i data-lucide="{{ $shipment->eventStateIcon($event->estado) }}" class="lucide-icon"></i>
                                                </span>
                                                <span>{{ $event->estado }}</span>
                                            </h3>
                                        </div>
                                        <time>{{ $event->fecha->format('d/m/Y H:i') }}</time>
                                    </div>
                                    <p>{{ $event->descripcion ?: 'Sin descripcion adicional.' }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Aun no hay eventos registrados para esta carga.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif
    </section>
</x-public-tracking-layout>
