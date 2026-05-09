<x-app-layout>
    @php
        $trackingStateOptions = collect($trackingStates)
            ->map(fn ($state) => ['value' => $state, 'label' => $state]);
    @endphp

    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Tracking {{ $shipment->nro_orden_trabajo }}</h1>
        <p class="text-sm text-slate-500">Cliente: {{ $shipment->client->razon_social }}</p>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        @if (auth()->user()->hasRole(['admin', 'operaciones']))
            <div class="fx-card fx-card-body">
                <h2 class="text-lg font-semibold text-slate-900">Registrar evento</h2>
                <form method="POST" action="{{ route('embarques.tracking.store', $shipment) }}" class="mt-6 space-y-5" data-disable-native-autocomplete="true">
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
                        />
                    </div>
                    <div>
                        <x-form-date-picker
                            id="fecha"
                            name="fecha"
                            label="Fecha"
                            :value="now()->format('Y-m-d H:i:S')"
                            placeholder="Seleccionar fecha y hora"
                            enable-time
                        />
                    </div>
                    <div>
                        <label class="fx-label" for="descripcion">Descripción</label>
                        <textarea class="fx-textarea" id="descripcion" name="descripcion" rows="4"></textarea>
                    </div>
                    <button class="fx-btn-primary" type="submit">Guardar evento</button>
                </form>
            </div>
        @endif

        <div class="fx-card">
            <div class="fx-card-header"><h2 class="text-lg font-semibold text-slate-900">Línea de tiempo</h2></div>
            <div class="space-y-4 p-6">
                @forelse ($shipment->events as $event)
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <p class="font-semibold text-slate-900">{{ $event->estado }}</p>
                            <span class="text-sm text-slate-500">{{ $event->fecha->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">{{ $event->descripcion ?: 'Sin descripción adicional.' }}</p>
                        <p class="mt-2 text-xs uppercase tracking-wide text-slate-400">Actualizado por {{ $event->user->name }}</p>
                    </div>
                @empty
                    <p class="p-6 text-sm text-slate-500">Sin eventos registrados.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
