@use('App\Support\Money')
@use('App\Support\GroupedList')
@php
    $canManage = auth()->user()->can('manage-provisions');
    $defaultGrouping = \App\Http\Controllers\ProvisionController::GROUPINGS[0];
    // Parametros que se conservan al cambiar de vista, buscar o agrupar.
    $params = fn (array $changes = []) => array_filter(array_merge([
        'vista' => $view,
        'search' => $search,
        'agrupar' => $grouping !== $defaultGrouping ? $grouping : null,
    ], $changes), fn ($value) => filled($value));
    $viewIcons = ['pendientes' => 'clock', 'pagados' => 'check-circle', 'todos' => 'list'];
    $views = collect(['pendientes' => 'Por pagar', 'pagados' => 'Pagados', 'todos' => 'Todos'])
        ->map(fn ($label, $key) => [
            'label' => $label,
            'icon' => $viewIcons[$key],
            'count' => $counts[$key],
            'alert' => $key === 'pendientes',
            'url' => route('provisiones.index', $params(['vista' => $key])),
        ])->all();
    $groupOptions = ['proveedor' => 'Proveedor', 'ro' => 'RO', 'ninguno' => 'Sin agrupar'];
    $columns = $canManage ? 7 : 6;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1>Provisiones</h1>
    </x-slot>

    <x-slot name="actions">
        @if ($canManage)
            <button type="button" class="fx-btn-primary" x-data x-on:click="$dispatch('open-dialog', 'nueva-provision')">Registrar costo</button>
        @endif
    </x-slot>

    @if ($canManage && $form)
        <x-dialog name="nueva-provision" title="Registrar costo de proveedor" max-width="max-w-3xl"
                  :show="$errors->any() && old('_form') === 'provision'">
            <form method="POST" action="{{ route('provisiones.store') }}" data-disable-native-autocomplete="true">
                @include('provisions._form', $form + ['inDialog' => true])
            </form>
        </x-dialog>
    @endif

    <x-view-tabs :views="$views" :current="$view" />

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('provisiones.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="vista" value="{{ $view }}">
            @if ($grouping !== $defaultGrouping)
                <input type="hidden" name="agrupar" value="{{ $grouping }}">
            @endif
            <label for="search" class="sr-only">Buscar</label>
            <input id="search" type="search" name="search" value="{{ $search }}" placeholder="Buscar OT, proveedor, factura u operación" class="fx-input mt-0 w-full sm:w-80">
            <button type="submit" class="sr-only">Buscar</button>
            @if ($search !== '')
                <a href="{{ route('provisiones.index', $params(['search' => null])) }}" class="fx-btn-link-primary px-2">Limpiar</a>
            @endif
        </form>

        <x-group-selector :options="$groupOptions" :current="$grouping"
            :url-for="fn ($key) => route('provisiones.index', $params(['agrupar' => $key === $defaultGrouping ? null : $key]))" />
    </div>

    <div class="mt-4 overflow-x-auto rounded-md border border-slate-200 bg-white">
        <table class="fx-table">
            <thead>
                <tr>
                    <th class="fx-th">{{ ['proveedor' => 'Proveedor · RO', 'ro' => 'RO · proveedor', 'ninguno' => 'Embarque'][$grouping] }}</th>
                    <th class="fx-th">{{ $grouping === 'ninguno' ? 'Proveedor · concepto' : 'Concepto' }}</th>
                    <th class="fx-th">Factura</th>
                    <th class="fx-th">Fecha</th>
                    <th class="fx-th">Pago</th>
                    <th class="fx-th text-right">Monto</th>
                    @if ($canManage)
                        <th class="fx-th"><span class="sr-only">Acciones</span></th>
                    @endif
                </tr>
            </thead>

            @if ($groups)
                @forelse ($groups as $group)
                    @php
                        // Se abren solos: los de un registro, los de una busqueda y si es el unico grupo.
                        $autoOpen = $group->documentos === 1 || $search !== '' || $groups->count() === 1;
                        $hidden = $group->items->count() - GroupedList::ITEMS_PER_GROUP;
                    @endphp
                    <tbody x-data="{ open: @js($autoOpen) }" class="divide-y divide-slate-100 border-t-2 border-slate-200">
                        <x-group-header :group="$group" :colspan="5" :trailing="$canManage ? 1 : 0"
                            :noun="['provisión', 'provisiones']" open-label="por pagar" />
                        @foreach ($group->items->take(GroupedList::ITEMS_PER_GROUP) as $provision)
                            @include('provisions._row', ['rowAttributes' => 'x-show="open"'.($autoOpen ? '' : ' x-cloak')])
                        @endforeach
                        @if ($hidden > 0)
                            <tr x-show="open" @unless ($autoOpen) x-cloak @endunless>
                                <td class="fx-td text-sm text-slate-500" colspan="{{ $columns }}">
                                    Y {{ $hidden }} más.
                                    <a href="{{ route('provisiones.index', $params(['agrupar' => 'ninguno', 'search' => $group->searchTerm])) }}" class="fx-btn-link-primary">Ver todas sin agrupar</a>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody><tr><td colspan="{{ $columns }}" class="fx-td py-10 text-center text-slate-500">Sin costos en esta vista.</td></tr></tbody>
                @endforelse
            @else
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($provisions as $provision)
                        @include('provisions._row')
                    @empty
                        <tr><td colspan="{{ $columns }}" class="fx-td py-10 text-center text-slate-500">Sin costos en esta vista.</td></tr>
                    @endforelse
                </tbody>
            @endif

            @if ($totals !== [])
                <tfoot>
                    <tr class="border-t border-slate-200 bg-slate-50">
                        <td class="fx-td font-medium text-slate-700" colspan="5">Total de la vista</td>
                        <td class="fx-td whitespace-nowrap text-right font-semibold text-slate-900">{{ Money::formatTotals($totals) }}</td>
                        @if ($canManage)<td class="fx-td"></td>@endif
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @php $paginator = $groups ?? $provisions; @endphp
    @if ($paginator->hasPages())
        <div class="mt-4">{{ $paginator->links() }}</div>
    @endif
</x-app-layout>
