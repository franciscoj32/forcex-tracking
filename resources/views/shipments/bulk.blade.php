{{-- Actualizacion masiva: RO agrupadas por nave y viaje --}}
@php
    $groupParams = fn ($group) => array_filter(['nave' => $group->nave, 'viaje' => $group->viaje, 'q' => $search ?: null]);
    $isSelected = fn ($group) => $group->nave === $nave && ($group->viaje ?? null) === $viaje;
    $stateOptions = collect($states)->map(fn ($state) => ['value' => $state, 'label' => $state]);
    $max = \App\Http\Controllers\ShipmentBulkController::MAX_SHIPMENTS;
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m') : '—';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1>Actualización masiva</h1>
    </x-slot>

    <x-slot name="actions">
        <a href="{{ route('embarques.index') }}" class="fx-btn-secondary">Volver a Embarques</a>
    </x-slot>

    <div class="grid items-start gap-4 xl:grid-cols-[22rem_minmax(0,1fr)]">
        {{-- 1. Elegir nave y viaje --}}
        <section class="fx-card">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="fx-section-title">1. Elige la nave y el viaje</h2>
                <p class="text-xs text-slate-500">Solo RO activas con nave registrada.</p>
            </div>
            <form method="GET" action="{{ route('embarques.masivo') }}" class="border-b border-slate-200 p-3" role="search">
                <label for="q" class="sr-only">Buscar nave</label>
                <input id="q" type="search" name="q" value="{{ $search }}" placeholder="Buscar nave, viaje o naviera" class="fx-input mt-0">
            </form>

            @if ($groups->isEmpty())
                <p class="px-4 py-4 text-sm text-slate-500">
                    {{ $search !== '' ? 'Ninguna nave coincide con la búsqueda.' : 'Todavía no hay RO activas con nave registrada.' }}
                </p>
            @else
                <ul class="max-h-[32rem] divide-y divide-slate-100 overflow-y-auto">
                    @foreach ($groups as $group)
                        <li>
                            <a href="{{ route('embarques.masivo', $groupParams($group)) }}"
                               @class([
                                   'flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-slate-50',
                                   'bg-brand-50 hover:bg-brand-50' => $isSelected($group),
                               ])
                               @if ($isSelected($group)) aria-current="true" @endif>
                                <span class="min-w-0">
                                    <span @class(['block truncate font-medium', 'text-brand-700' => $isSelected($group), 'text-slate-900' => ! $isSelected($group)])>
                                        {{ $group->nave }}{{ $group->viaje ? ' · '.$group->viaje : '' }}
                                    </span>
                                    <span class="block truncate text-xs text-slate-500">
                                        {{ $group->naviera ?: 'Sin naviera' }} · ETA {{ $date($group->eta_desde) }}{{ $group->eta_hasta !== $group->eta_desde ? ' – '.$date($group->eta_hasta) : '' }}
                                    </span>
                                </span>
                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-700">{{ $group->total }} RO</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($withoutVessel > 0)
                <p class="border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
                    {{ $withoutVessel }} RO activas no tienen nave registrada y no aparecen aquí. Se completa en Editar RO → Datos operativos.
                </p>
            @endif
        </section>

        {{-- 2. Elegir RO y aplicar --}}
        <div class="space-y-4">
            @if ($result)
                <section class="fx-card p-4">
                    @if ($result['done'] !== [])
                        <p class="text-sm text-slate-700"><span class="font-semibold text-accent-700">Aplicado:</span> {{ implode(', ', $result['done']) }}</p>
                    @endif
                    @if ($result['skipped'] !== [])
                        <p class="mt-2 text-sm font-semibold text-amber-700">Omitidas ({{ count($result['skipped']) }}):</p>
                        <ul class="mt-1 space-y-0.5 text-sm text-slate-700">
                            @foreach ($result['skipped'] as [$ro, $reason])
                                <li><span class="font-medium">{{ $ro }}:</span> {{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            @if ($nave === null)
                <section class="fx-card px-4 py-10 text-center text-slate-500">
                    Elige una nave y un viaje para ver sus RO y actualizarlas juntas.
                </section>
            @elseif ($shipments->isEmpty())
                <section class="fx-card px-4 py-10 text-center text-slate-500">
                    No hay RO activas en {{ $nave }}{{ $viaje ? ' · viaje '.$viaje : '' }}.
                </section>
            @else
                <div x-data="{ selected: @js($shipments->take($max)->pluck('id')->all()), all: @js($shipments->take($max)->pluck('id')->all()) }" class="space-y-4">
                    <section class="fx-card">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                            <div>
                                <h2 class="fx-section-title">2. {{ $nave }}{{ $viaje ? ' · viaje '.$viaje : '' }}</h2>
                                <p class="text-xs text-slate-500">
                                    <span x-text="selected.length"></span> de {{ $shipments->count() }} RO seleccionadas
                                    @if ($shipments->count() > $max) · máximo {{ $max }} por acción @endif
                                </p>
                            </div>
                            <button type="button" class="fx-btn-link-primary text-sm"
                                    x-on:click="selected = selected.length === all.length ? [] : [...all]"
                                    x-text="selected.length === all.length ? 'Quitar todas' : 'Seleccionar todas'">Quitar todas</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="fx-table">
                                <thead>
                                    <tr>
                                        <th class="fx-th w-10"><span class="sr-only">Elegir</span></th>
                                        <th class="fx-th">RO</th>
                                        <th class="fx-th">Cliente</th>
                                        <th class="fx-th">Tipo</th>
                                        <th class="fx-th">Estado</th>
                                        <th class="fx-th">ETD</th>
                                        <th class="fx-th">ETA</th>
                                        <th class="fx-th">Customer</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($shipments as $shipment)
                                        <tr>
                                            <td class="fx-td">
                                                <input type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600"
                                                       value="{{ $shipment->id }}" x-model.number="selected" aria-label="Elegir {{ $shipment->routing_order }}">
                                            </td>
                                            <td class="fx-td whitespace-nowrap">
                                                <a href="{{ route('embarques.show', $shipment) }}" class="font-medium text-slate-900 hover:underline">{{ $shipment->routing_order }}</a>
                                            </td>
                                            <td class="fx-td">{{ $shipment->client?->razon_social }}</td>
                                            <td class="fx-td">{{ $shipment->tipo_carga }}</td>
                                            <td class="fx-td whitespace-nowrap">{{ $shipment->estado_actual }}</td>
                                            <td class="fx-td whitespace-nowrap tabular-nums">{{ $shipment->fecha_etd?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="fx-td whitespace-nowrap tabular-nums">{{ $shipment->fecha_eta?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="fx-td">{{ $shipment->customerService?->name ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="grid gap-4 lg:grid-cols-2">
                        @if ($canChangeDates)
                            <section class="fx-card">
                                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">3a. Cambiar ETD o ETA</h2>
                                <form method="POST" action="{{ route('embarques.masivo.fechas') }}" class="space-y-3 p-4"
                                      onsubmit="return confirm('¿Cambiar la fecha de las RO seleccionadas?')">
                                    @csrf
                                    <input type="hidden" name="nave" value="{{ $nave }}">
                                    <input type="hidden" name="viaje" value="{{ $viaje }}">
                                    <template x-for="id in selected" :key="id"><input type="hidden" name="shipment_ids[]" :value="id"></template>
                                    <fieldset>
                                        <legend class="fx-label">Fecha a cambiar</legend>
                                        <div class="mt-1 flex gap-4 text-sm text-slate-700">
                                            <label class="inline-flex items-center gap-2"><input type="radio" name="campo" value="fecha_eta" class="text-brand-600 focus:ring-brand-600" @checked(old('campo', 'fecha_eta') === 'fecha_eta')> ETA</label>
                                            <label class="inline-flex items-center gap-2"><input type="radio" name="campo" value="fecha_etd" class="text-brand-600 focus:ring-brand-600" @checked(old('campo') === 'fecha_etd')> ETD</label>
                                        </div>
                                    </fieldset>
                                    <x-form-date-picker id="bulk_fecha_nueva" name="fecha" label="Nueva fecha" placeholder="Seleccionar fecha" required />
                                    <p class="text-xs text-slate-500">El cliente no la ve todavía: el Customer la valida para todas juntas y recibe un solo correo.</p>
                                    <button type="submit" class="fx-btn-primary w-full" :disabled="selected.length === 0"
                                            x-text="'Aplicar a ' + selected.length + ' RO'">Aplicar</button>
                                </form>
                            </section>
                        @endif

                        <section class="fx-card">
                            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">{{ $canChangeDates ? '3b' : '3' }}. Registrar hito</h2>
                            <form method="POST" action="{{ route('embarques.masivo.hitos') }}" class="space-y-3 p-4" data-disable-native-autocomplete="true"
                                  onsubmit="return confirm('¿Registrar el hito en las RO seleccionadas?')">
                                @csrf
                                <input type="hidden" name="nave" value="{{ $nave }}">
                                <input type="hidden" name="viaje" value="{{ $viaje }}">
                                <template x-for="id in selected" :key="id"><input type="hidden" name="shipment_ids[]" :value="id"></template>
                                <x-form-combobox id="bulk_estado" name="estado" label="Hito" :options="$stateOptions"
                                    :value="old('estado', 'Arribo a puerto')" placeholder="Seleccionar hito"
                                    search-placeholder="Buscar hito..." empty-text="No hay hitos que coincidan." searchable required />
                                <x-form-date-picker id="bulk_hito_fecha" name="fecha" label="Fecha y hora"
                                    :value="now()->format('Y-m-d H:i')" placeholder="Seleccionar fecha y hora" enable-time required />
                                <div>
                                    <label class="fx-label" for="bulk_descripcion">Nota interna (el cliente no la ve)</label>
                                    <textarea class="fx-textarea" id="bulk_descripcion" name="descripcion" rows="2" maxlength="1000" placeholder="Detalle operativo"></textarea>
                                </div>
                                <p class="text-xs text-slate-500">Las RO que ya pasaron ese hito se omiten y te decimos cuáles.</p>
                                <button type="submit" class="fx-btn-primary w-full" :disabled="selected.length === 0"
                                        x-text="'Registrar en ' + selected.length + ' RO'">Registrar</button>
                            </form>
                        </section>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
