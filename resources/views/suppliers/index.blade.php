@php
    $canManage = auth()->user()->can('manage-suppliers');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1>Proveedores</h1>
    </x-slot>

    @if ($canManage)
        <x-slot name="actions">
            <button type="button" class="fx-btn-primary" x-data x-on:click="$dispatch('open-new-supplier')">Nuevo proveedor</button>
        </x-slot>
    @endif

    <div x-data="{ creating: {{ $errors->any() ? 'true' : 'false' }} }">

        <form method="GET" action="{{ route('proveedores.index') }}" class="flex flex-wrap items-center gap-2">
            <label for="q" class="sr-only">Buscar</label>
            <input id="q" type="search" name="q" value="{{ $search }}" placeholder="Buscar por razón social o RUC" class="fx-input mt-0 w-full sm:w-80">
            <button type="submit" class="sr-only">Buscar</button>
            @if ($search)
                <a href="{{ route('proveedores.index') }}" class="fx-btn-link-primary px-2">Limpiar</a>
            @endif
        </form>

        <div class="mt-4 overflow-x-auto rounded-md border border-slate-200 bg-white">
            <table class="fx-table">
                <thead>
                    <tr>
                        <th class="fx-th">Razón social</th>
                        <th class="fx-th">RUC / Tax ID</th>
                        <th class="fx-th">Tipo</th>
                        <th class="fx-th">Contacto</th>
                        <th class="fx-th text-right">Costos</th>
                        @if ($canManage)
                            <th class="fx-th"><span class="sr-only">Acciones</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($suppliers as $supplier)
                        <tr x-data="{ editing: false }" class="hover:bg-slate-50">
                            <td class="fx-td">
                                <span class="font-medium text-slate-900">{{ $supplier->razon_social }}</span>
                                @unless ($supplier->activo)
                                    <span class="fx-badge ml-1 bg-slate-100 text-slate-600">Inactivo</span>
                                @endunless
                            </td>
                            <td class="fx-td tabular-nums">{{ $supplier->ruc ?: '—' }}</td>
                            <td class="fx-td">{{ $supplier->typeLabel() }}</td>
                            <td class="fx-td">{{ collect([$supplier->correo, $supplier->telefono])->filter()->join(' · ') ?: '—' }}</td>
                            <td class="fx-td text-right tabular-nums">{{ $supplier->provisions_count }}</td>
                            @if ($canManage)
                                <td class="fx-td text-right">
                                    <button type="button" class="fx-btn-link-primary" x-on:click="editing = true">Editar</button>
                                    <template x-teleport="body">
                                        <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-center sm:p-4" x-on:keydown.escape.window="editing = false">
                                            <form method="POST" action="{{ route('proveedores.update', $supplier) }}" class="w-full max-w-2xl rounded-t-md bg-white p-5 text-left shadow-lg sm:rounded-md" x-on:click.outside="editing = false">
                                                @csrf
                                                @method('PUT')
                                                <h3 class="fx-section-title">Editar {{ $supplier->razon_social }}</h3>
                                                @include('suppliers._fields', ['supplier' => $supplier, 'prefix' => 'edit_'.$supplier->id])
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
                    @empty
                        <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="fx-td py-10 text-center text-slate-500">Sin proveedores.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($suppliers->hasPages())
            <div class="mt-4">{{ $suppliers->links() }}</div>
        @endif

        @if ($canManage)
            <template x-teleport="body">
                <div x-show="creating" x-cloak x-on:open-new-supplier.window="creating = true" x-on:keydown.escape.window="creating = false"
                     class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-center sm:p-4">
                    <form method="POST" action="{{ route('proveedores.store') }}" class="w-full max-w-2xl rounded-t-md bg-white p-5 shadow-lg sm:rounded-md" x-on:click.outside="creating = false">
                        @csrf
                        <h3 class="fx-section-title">Nuevo proveedor</h3>
                        @include('suppliers._fields', ['supplier' => new \App\Models\Supplier(['tipo' => 'naviera', 'activo' => true]), 'prefix' => 'new'])
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" class="fx-btn-secondary" x-on:click="creating = false">Cancelar</button>
                            <button type="submit" class="fx-btn-primary">Registrar</button>
                        </div>
                    </form>
                </div>
            </template>
        @endif
    </div>
</x-app-layout>
