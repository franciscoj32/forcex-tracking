@php
    $roleMatrix = [
        [
            'module' => 'Usuarios internos',
            'admin' => 'Crear, editar, activar e inactivar.',
            'operaciones' => 'No.',
            'comercial' => 'No.',
            'cliente' => 'No.',
        ],
        [
            'module' => 'Clientes',
            'admin' => 'Crear, editar y consultar.',
            'operaciones' => 'Consulta.',
            'comercial' => 'Consulta.',
            'cliente' => 'No.',
        ],
        [
            'module' => 'Embarques',
            'admin' => 'Control total.',
            'operaciones' => 'Crear, editar y operar cualquier embarque.',
            'comercial' => 'Consulta.',
            'cliente' => 'Solo sus embarques.',
        ],
        [
            'module' => 'Tracking y estados',
            'admin' => 'Registrar y corregir.',
            'operaciones' => 'Registrar eventos y mover estados.',
            'comercial' => 'Consulta.',
            'cliente' => 'Consulta propia.',
        ],
        [
            'module' => 'Documentos',
            'admin' => 'Subir, descargar y eliminar.',
            'operaciones' => 'Subir, descargar y eliminar.',
            'comercial' => 'Consulta y descarga interna.',
            'cliente' => 'Descarga propia.',
        ],
        [
            'module' => 'Facturacion',
            'admin' => 'Gestion completa.',
            'operaciones' => 'Gestion operativa.',
            'comercial' => 'Consulta.',
            'cliente' => 'No.',
        ],
        [
            'module' => 'Provisiones',
            'admin' => 'Gestion completa.',
            'operaciones' => 'Gestion operativa.',
            'comercial' => 'Consulta.',
            'cliente' => 'No.',
        ],
        [
            'module' => 'Codigo publico de rastreo',
            'admin' => 'Ver y compartir.',
            'operaciones' => 'Ver y compartir.',
            'comercial' => 'Ver y compartir.',
            'cliente' => 'Usa su pagina publica.',
        ],
        [
            'module' => 'Usuarios vinculados a embarque',
            'admin' => 'Asignar responsables y seguimiento.',
            'operaciones' => 'Asignar responsables y seguimiento.',
            'comercial' => 'No.',
            'cliente' => 'No.',
        ],
        [
            'module' => 'Endoso',
            'admin' => 'Puede supervisar o asumir.',
            'operaciones' => 'Puede ser responsable de endoso.',
            'comercial' => 'No debe asumir endoso.',
            'cliente' => 'No.',
        ],
    ];

    $permissionTone = function (string $value): string {
        $normalized = strtolower($value);

        return match (true) {
            str_contains($normalized, 'no') => 'border-slate-200 bg-slate-100 text-slate-600',
            str_contains($normalized, 'control total'), str_contains($normalized, 'gestion completa'), str_contains($normalized, 'crear, editar'), str_contains($normalized, 'subir, descargar y eliminar') => 'border-cyan-200 bg-cyan-50 text-cyan-700',
            str_contains($normalized, 'consulta'), str_contains($normalized, 'ver y compartir'), str_contains($normalized, 'descarga propia') => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            str_contains($normalized, 'solo'), str_contains($normalized, 'usa su pagina publica') => 'border-amber-200 bg-amber-50 text-amber-700',
            default => 'border-blue-200 bg-blue-50 text-blue-700',
        };
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Roles del sistema</h1>
        <p class="text-sm text-slate-500">Consulta rapida de permisos y alcance por tipo de usuario.</p>
    </x-slot>

    <div class="space-y-6">
        <section class="fx-card overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50 p-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Resumen general</h2>
                    <p class="mt-1 text-sm text-slate-500">Matriz base para explicar que puede hacer cada rol dentro del sistema.</p>
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    <div class="w-[180px] rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm shadow-slate-100/70">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Roles internos</p>
                        <div class="mt-2 flex items-end justify-between gap-3">
                            <p class="text-3xl font-semibold leading-none text-slate-900">3</p>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">Activos</span>
                        </div>
                    </div>
                    <div class="w-[180px] rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm shadow-slate-100/70">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Modulos clave</p>
                        <div class="mt-2 flex items-end justify-between gap-3">
                            <p class="text-3xl font-semibold leading-none text-slate-900">{{ count($roleMatrix) }}</p>
                            <span class="rounded-full bg-cyan-50 px-2.5 py-1 text-[11px] font-semibold text-cyan-700">Matriz</span>
                        </div>
                    </div>
                    <div class="w-[180px] rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm shadow-slate-100/70">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Cliente</p>
                        <div class="mt-2 space-y-1">
                            <p class="text-sm font-semibold leading-5 text-slate-900">Acceso solo propio</p>
                            <p class="text-xs text-slate-500">Sin vista global</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-sm shadow-slate-100/70">
            <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-5">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Tabla de roles</h2>
                        <p class="mt-1 text-sm text-slate-500">Permisos visibles por modulo y por perfil de usuario.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-semibold">
                        <span class="rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-cyan-700">Control</span>
                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-emerald-700">Consulta</span>
                        <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-amber-700">Limitado</span>
                        <span class="rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-slate-600">Sin acceso</span>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto px-4 py-4">
                <table class="min-w-[980px] w-full border-separate border-spacing-y-3">
                    <thead>
                        <tr>
                            <th class="px-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Modulo</th>
                            <th class="px-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Admin</th>
                            <th class="px-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Operaciones</th>
                            <th class="px-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Comercial</th>
                            <th class="px-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Cliente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roleMatrix as $row)
                            <tr>
                                <td class="rounded-l-2xl border border-r-0 border-slate-200 bg-slate-50 px-4 py-4 align-top">
                                    <p class="text-sm font-semibold text-slate-900">{{ $row['module'] }}</p>
                                </td>
                                @foreach (['admin', 'operaciones', 'comercial', 'cliente'] as $column)
                                    <td class="{{ $loop->last ? 'rounded-r-2xl' : '' }} border {{ $loop->last ? '' : 'border-r-0' }} border-slate-200 bg-white px-4 py-4 align-top">
                                        <span class="inline-flex rounded-2xl border px-3 py-2 text-sm font-medium leading-6 {{ $permissionTone($row[$column]) }}">
                                            {{ $row[$column] }}
                                        </span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
