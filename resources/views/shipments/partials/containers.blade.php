{{-- Contenedores de la RO (no aplica a carga aerea). Los gestiona Operaciones. --}}
@php
    $canManageContainers = auth()->user()->can('manageContainers', $shipment);
    $containerTypes = \App\Models\ShipmentContainer::TYPE_LABELS;
    $containerFields = fn ($container, string $prefix) => ['container' => $container, 'prefix' => $prefix, 'containerTypes' => $containerTypes];
    $openNew = $errors->hasAny(['numero', 'tipo']);
@endphp

<section class="fx-card scroll-mt-20" id="contenedores" x-data="{ adding: {{ $openNew ? 'true' : 'false' }}, dating: false }">
    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
        <h2 class="fx-section-title">Contenedores <span class="font-normal text-slate-500">({{ $shipment->containers->count() }})</span></h2>
        @if ($canManageContainers)
            <div class="flex items-center gap-4">
                @if ($shipment->containers->count() > 1)
                    <button type="button" class="fx-btn-link-primary" x-on:click="dating = ! dating; adding = false" x-text="dating ? 'Cancelar' : 'Retiro / devolución a todos'">Retiro / devolución a todos</button>
                @endif
                <button type="button" class="fx-btn-link-primary" x-on:click="adding = ! adding; dating = false" x-text="adding ? 'Cancelar' : 'Agregar contenedor'">Agregar contenedor</button>
            </div>
        @endif
    </div>

    @if ($canManageContainers)
        <form x-show="adding" x-cloak method="POST" action="{{ route('embarques.contenedores.store', $shipment) }}" class="border-b border-slate-200 bg-slate-50 px-4 pb-4">
            @csrf
            @include('shipments.partials.container-fields', $containerFields(new \App\Models\ShipmentContainer(['tipo' => '40HC']), 'new'))
            <button type="submit" class="fx-btn-primary mt-4">Guardar contenedor</button>
        </form>

        @if ($shipment->containers->count() > 1)
            <form x-show="dating" x-cloak method="POST" action="{{ route('embarques.contenedores.fechas', $shipment) }}" class="border-b border-slate-200 bg-slate-50 px-4 pb-4">
                @csrf
                @method('PUT')
                <p class="pt-4 text-sm text-slate-600">Se registra en los contenedores que aún no tienen esa fecha. Las fechas ya registradas no cambian.</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                    <x-form-date-picker id="all_fecha_retiro" name="fecha_retiro" label="Retiro lleno del terminal" placeholder="Seleccionar fecha" />
                    <x-form-date-picker id="all_fecha_devolucion" name="fecha_devolucion" label="Devolución del vacío" placeholder="Seleccionar fecha" />
                </div>
                <button type="submit" class="fx-btn-primary mt-4">Aplicar a todos</button>
            </form>
        @endif
    @endif

    @if ($shipment->containers->isEmpty())
        <p class="px-4 py-4 text-slate-500">Sin contenedores.</p>
    @else
        <div class="overflow-x-auto">
            <table class="fx-table">
                <thead>
                    <tr>
                        <th class="fx-th">Número</th>
                        <th class="fx-th">Tipo</th>
                        <th class="fx-th">Precinto</th>
                        <th class="fx-th text-right">Bultos</th>
                        <th class="fx-th text-right">Peso</th>
                        <th class="fx-th text-right">CBM</th>
                        <th class="fx-th">Retiro</th>
                        <th class="fx-th">Devolución</th>
                        @if ($canManageContainers)
                            <th class="fx-th"><span class="sr-only">Acciones</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($shipment->containers as $container)
                        <tr x-data="{ editing: false }">
                            <td class="fx-td whitespace-nowrap font-mono text-slate-900">{{ $container->numero }}</td>
                            <td class="fx-td">{{ $container->tipo }}</td>
                            <td class="fx-td">{{ $container->precinto ?: '—' }}</td>
                            <td class="fx-td text-right">{{ $container->bultos ?? '—' }}</td>
                            <td class="fx-td whitespace-nowrap text-right">{{ $container->peso_kg !== null ? \App\Models\Shipment::formatTons($container->peso_kg) : '—' }}</td>
                            <td class="fx-td text-right">{{ $container->volumen_cbm ?? '—' }}</td>
                            <td class="fx-td whitespace-nowrap tabular-nums">{{ $container->fecha_retiro?->format('d/m/Y') ?? 'Sin retirar' }}</td>
                            <td class="fx-td whitespace-nowrap tabular-nums">{{ $container->fecha_devolucion?->format('d/m/Y') ?? ($container->fecha_retiro ? 'Por devolver' : '—') }}</td>
                            @if ($canManageContainers)
                                <td class="fx-td">
                                    <div class="flex justify-end gap-3">
                                        <button type="button" class="fx-btn-link-primary" x-on:click="editing = true">Editar</button>
                                        <form method="POST" action="{{ route('contenedores.destroy', $container) }}" onsubmit="return confirm('¿Eliminar el contenedor {{ $container->numero }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm font-medium text-rose-700 hover:underline">Eliminar</button>
                                        </form>
                                    </div>
                                    <template x-teleport="body">
                                        <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-center sm:p-4" x-on:keydown.escape.window="editing = false">
                                            <form method="POST" action="{{ route('contenedores.update', $container) }}" class="w-full max-w-2xl rounded-t-md bg-white p-5 shadow-lg sm:rounded-md" x-on:click.outside="editing = false">
                                                @csrf
                                                @method('PUT')
                                                <h3 class="fx-section-title">Editar contenedor {{ $container->numero }}</h3>
                                                @include('shipments.partials.container-fields', $containerFields($container, 'edit_'.$container->id))
                                                <div class="mt-5 flex justify-end gap-2">
                                                    <button type="button" class="fx-btn-secondary" x-on:click="editing = false">Cancelar</button>
                                                    <button type="submit" class="fx-btn-primary">Guardar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </template>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
