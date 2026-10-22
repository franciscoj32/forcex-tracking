<x-app-layout>
    <x-slot name="header">
        <h1>Conceptos de tarifa</h1>
    </x-slot>

    <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="overflow-x-auto rounded-md border border-slate-200 bg-white">
            <table class="fx-table">
                <thead>
                    <tr>
                        <th class="fx-th w-16">Orden</th>
                        <th class="fx-th">Concepto</th>
                        <th class="fx-th">Código</th>
                        <th class="fx-th text-right">Tarifas · costos</th>
                        <th class="fx-th"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($concepts as $concept)
                        <tr x-data="{ editing: false }" class="hover:bg-slate-50">
                            <td class="fx-td tabular-nums text-slate-500">{{ $concept->orden }}</td>
                            <td class="fx-td">
                                <span x-show="! editing" class="text-slate-900">
                                    {{ $concept->nombre }}
                                    @unless ($concept->activo)
                                        <span class="fx-badge ml-1 bg-slate-100 text-slate-600">Inactivo</span>
                                    @endunless
                                </span>
                                <form x-show="editing" x-cloak method="POST" action="{{ route('conceptos.update', $concept) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <label class="sr-only" for="nombre_{{ $concept->id }}">Nombre</label>
                                    <input class="fx-input mt-0 min-w-[12rem] flex-1" id="nombre_{{ $concept->id }}" name="nombre" value="{{ $concept->nombre }}" required>
                                    <label class="sr-only" for="orden_{{ $concept->id }}">Orden</label>
                                    <input class="fx-input mt-0 w-20" id="orden_{{ $concept->id }}" name="orden" type="number" min="0" value="{{ $concept->orden }}">
                                    <input type="hidden" name="activo" value="0">
                                    <label class="inline-flex items-center gap-1.5 text-sm">
                                        <input type="checkbox" name="activo" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/30" @checked($concept->activo)> Activo
                                    </label>
                                    <button type="submit" class="fx-btn-primary">Guardar</button>
                                </form>
                            </td>
                            <td class="fx-td font-mono text-xs text-slate-500">{{ $concept->codigo }}</td>
                            <td class="fx-td text-right tabular-nums text-slate-600">{{ $concept->rates_count }} · {{ $concept->provisions_count }}</td>
                            <td class="fx-td text-right">
                                <button type="button" class="fx-btn-link-primary" x-on:click="editing = ! editing" x-text="editing ? 'Cerrar' : 'Editar'">Editar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="fx-card">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Nuevo concepto</h2>
            <form method="POST" action="{{ route('conceptos.store') }}" class="space-y-3 p-4">
                @csrf
                <div>
                    <label class="fx-label" for="nombre">Nombre</label>
                    <input class="fx-input" id="nombre" name="nombre" value="{{ old('nombre') }}" placeholder="Ej. Seguro de carga" required>
                </div>
                <div>
                    <label class="fx-label" for="orden">Orden (opcional)</label>
                    <input class="fx-input" id="orden" name="orden" type="number" min="0" value="{{ old('orden') }}">
                </div>
                <button type="submit" class="fx-btn-primary w-full">Agregar</button>
                <p class="text-xs text-slate-500">Un concepto que ya se usó no se elimina: se desactiva.</p>
            </form>
        </section>
    </div>
</x-app-layout>
