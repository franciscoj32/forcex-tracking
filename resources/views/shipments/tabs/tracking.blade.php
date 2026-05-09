<div class="space-y-6">
    <div class="grid items-start gap-6 2xl:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 p-5">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Estado actual del embarque</h3>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-700">Actual</span>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-700">Completado</span>
                </div>
            </div>

            @php
                $currentStateDate = $currentEvent?->fecha?->format('d/m/Y H:i') ?: 'Sin fecha registrada';
                $currentStateDescription = $currentEvent?->descripcion ?: 'Sin comentario adicional para este hito.';
            @endphp

            <div class="mt-6 rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-amber-50/60 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex flex-col items-center pt-1">
                            <span class="fx-status-dot bg-amber-500"></span>
                            <span class="mt-3 text-xl font-bold leading-none text-amber-500">^</span>
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-xl font-semibold text-slate-900">{{ $shipment->estado_actual }}</p>
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Actual</span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $currentStateDescription }}</p>
                        </div>
                    </div>
                    <div class="text-sm font-medium text-slate-500">
                        {{ $currentStateDate }}
                    </div>
                </div>
            </div>

            @if ($nextState)
                <div class="mt-4 flex items-center gap-3 border-t border-rose-100 pt-4">
                    <span class="h-2.5 w-2.5 rounded-full bg-rose-500 shadow-[0_0_0_4px_rgba(244,63,94,0.12)]"></span>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-rose-500">Siguiente estado</p>
                    <p class="text-sm font-semibold text-rose-700">{{ $nextState }}</p>
                </div>
            @endif
        </div>
        @if ($canOperate)
            <div class="rounded-3xl border border-slate-200 p-5">
                <h3 class="text-lg font-semibold text-slate-900">Registrar nuevo hito</h3>
                <form method="POST" action="{{ route('embarques.tracking.store', $shipment) }}" class="mt-5 grid gap-4 md:grid-cols-2" data-disable-native-autocomplete="true">
                    @csrf
                    <div>
                        <x-form-combobox
                            id="estado"
                            name="estado"
                            label="Estado"
                            :options="$trackingStateOptions"
                            :value="old('estado', $shipment->estado_actual)"
                            placeholder="Seleccionar estado"
                            search-placeholder="Buscar estado..."
                            empty-text="No hay estados que coincidan."
                            searchable
                            required
                        />
                    </div>
                    <div>
                        <x-form-date-picker
                            id="fecha"
                            name="fecha"
                            label="Fecha"
                            :value="old('fecha', now()->format('Y-m-d H:i:S'))"
                            placeholder="Seleccionar fecha y hora"
                            enable-time
                            required
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="fx-label" for="descripcion">Descripción</label>
                        <textarea class="fx-textarea" id="descripcion" name="descripcion" rows="3" placeholder="Detalle operativo del avance">{{ old('descripcion') }}</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <button class="fx-btn-primary" type="submit">Guardar movimiento</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <div class="rounded-3xl border border-slate-200 p-5">
        <div class="mb-4 flex items-center gap-3">
            <div class="h-px flex-1 bg-slate-200"></div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Historial recorrido</p>
            <div class="h-px flex-1 bg-slate-200"></div>
        </div>

        <div class="space-y-4">
            @foreach ($completedStateIndexes as $index)
                @php
                    $state = $trackingStates[$index];
                    $event = $timelineByState->get($state);
                    $isCurrent = $index === $currentStateIndex;
                    $statusClass = $isCurrent ? 'bg-amber-500' : 'bg-emerald-500';
                    $cardClass = $isCurrent ? 'border-amber-200 bg-amber-50/70' : 'border-emerald-200 bg-emerald-50/70';
                    $label = $isCurrent ? 'Actual' : 'Completado';
                @endphp
                <div class="relative rounded-3xl border px-4 py-4 {{ $cardClass }}">
                    @if (! $loop->last)
                        <div class="pointer-events-none absolute bottom-[-20px] left-[1.15rem] top-[3.25rem] w-px bg-gradient-to-b from-slate-300 to-transparent"></div>
                    @endif
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-start gap-4">
                            <div class="flex flex-col items-center">
                                <span class="fx-status-dot {{ $statusClass }}"></span>
                                @if (! $loop->last)
                                    <span class="mt-3 text-lg font-bold leading-none text-slate-300">^</span>
                                @endif
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-slate-900">{{ $state }}</p>
                                    <span class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $label }}</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ $event?->descripcion ?: 'Sin comentario adicional para este hito.' }}
                                </p>
                            </div>
                        </div>
                        <div class="text-sm font-medium text-slate-500">
                            {{ $event?->fecha?->format('d/m/Y H:i') ?: 'Sin fecha registrada' }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
