@php
    // Refleja App\Policies, los Gates de AppServiceProvider y App\Support\ShipmentFieldAccess.
    $roleColumns = \App\Models\User::ROLE_LABELS;

    $roleMatrix = [
        ['module' => 'Usuarios internos', 'admin' => 'Crear, editar, activar e inactivar.', 'comercial' => 'No.', 'customer' => 'No.', 'operaciones' => 'No.', 'cliente' => 'No.'],
        ['module' => 'Clientes', 'admin' => 'Control total. Aprueba o rechaza y valida el crédito.', 'comercial' => 'Registra clientes (quedan pendientes) y corrige los suyos solo hasta su aprobación.', 'customer' => 'Consulta y mantiene contactos.', 'operaciones' => 'Consulta.', 'cliente' => 'No.'],
        ['module' => 'Crear RO', 'admin' => 'Control total.', 'comercial' => 'Solo para clientes aprobados; elige al Customer.', 'customer' => 'No.', 'operaciones' => 'No.', 'cliente' => 'No.'],
        ['module' => 'Datos comerciales (cliente, incoterm, Customer)', 'admin' => 'Control total.', 'comercial' => 'Solo sus RO y solo en "Creación Routing Order".', 'customer' => 'Consulta.', 'operaciones' => 'Consulta.', 'cliente' => 'No.'],
        ['module' => 'Condición de crédito del embarque', 'admin' => 'Hereda del cliente o la fuerza por embarque.', 'comercial' => 'Consulta (heredada).', 'customer' => 'Consulta.', 'operaciones' => 'Consulta.', 'cliente' => 'No.'],
        ['module' => 'Datos operativos (BL, naviera, fechas, pesos)', 'admin' => 'Control total.', 'comercial' => 'Consulta.', 'customer' => 'Consulta.', 'operaciones' => 'Edita: es dueño de estos datos.', 'cliente' => 'No.'],
        ['module' => 'Asignar operador', 'admin' => 'Control total.', 'comercial' => 'No.', 'customer' => 'Asigna al operador.', 'operaciones' => 'Consulta.', 'cliente' => 'No.'],
        ['module' => 'Tracking y estados', 'admin' => 'Registra estados.', 'comercial' => 'Consulta.', 'customer' => 'Registra estados.', 'operaciones' => 'Registra estados.', 'cliente' => 'Usa su pagina publica.'],
        ['module' => 'Documentos', 'admin' => 'Sube, descarga y elimina.', 'comercial' => 'Consulta y descarga.', 'customer' => 'Sube, descarga y elimina.', 'operaciones' => 'Sube, descarga y elimina.', 'cliente' => 'No.'],
        ['module' => 'Provisiones (costos)', 'admin' => 'Gestión completa.', 'comercial' => 'No.', 'customer' => 'Consulta.', 'operaciones' => 'Gestión operativa.', 'cliente' => 'No.'],
        ['module' => 'Facturación', 'admin' => 'Gestión completa.', 'comercial' => 'Consulta.', 'customer' => 'Consulta.', 'operaciones' => 'Gestión operativa.', 'cliente' => 'No.'],
        ['module' => 'Margen y reportes financieros', 'admin' => 'Toda la empresa.', 'comercial' => 'Solo sus RO.', 'customer' => 'No.', 'operaciones' => 'No.', 'cliente' => 'No.'],
        ['module' => 'Código público de rastreo', 'admin' => 'Ver y compartir.', 'comercial' => 'Ver y compartir.', 'customer' => 'Ver y compartir.', 'operaciones' => 'Ver y compartir.', 'cliente' => 'Usa su pagina publica.'],
    ];

    // Nivel de cada permiso: edita / limitado / consulta / sin acceso.
    $level = function (string $value): string {
        $normalized = mb_strtolower($value);

        return match (true) {
            $normalized === 'no.' => 'none',
            str_starts_with($normalized, 'consulta') && ! str_contains($normalized, 'mantiene'), str_contains($normalized, 'ver y compartir') => 'read',
            str_contains($normalized, 'solo'), str_contains($normalized, 'usa su pagina publica') => 'partial',
            default => 'full',
        };
    };
    $levels = [
        'full' => ['symbol' => '●', 'class' => 'text-accent-600', 'label' => 'Edita'],
        'partial' => ['symbol' => '◐', 'class' => 'text-amber-500', 'label' => 'Limitado'],
        'read' => ['symbol' => '○', 'class' => 'text-brand-500', 'label' => 'Consulta'],
        'none' => ['symbol' => '—', 'class' => 'text-slate-300', 'label' => 'Sin acceso'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1>Permisos por rol</h1>
    </x-slot>

    <div class="mb-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-600">
        @foreach ($levels as $item)
            <span class="inline-flex items-center gap-1.5"><span class="{{ $item['class'] }}">{{ $item['symbol'] }}</span>{{ $item['label'] }}</span>
        @endforeach
        <span class="text-slate-500">· El cliente no tiene acceso al sistema: consulta su carga con el código de rastreo.</span>
    </div>

    <div class="overflow-x-auto rounded-md border border-slate-200 bg-white">
        <table class="fx-table min-w-[960px]">
            <thead>
                <tr>
                    <th class="fx-th w-56">Módulo</th>
                    @foreach ($roleColumns as $roleLabel)
                        <th class="fx-th">{{ $roleLabel }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach ($roleMatrix as $row)
                    <tr>
                        <td class="fx-td align-top font-medium text-slate-900">{{ $row['module'] }}</td>
                        @foreach (array_keys($roleColumns) as $column)
                            @php $cellLevel = $levels[$level($row[$column])]; @endphp
                            <td class="fx-td align-top">
                                <span class="flex gap-2">
                                    <span class="{{ $cellLevel['class'] }}" title="{{ $cellLevel['label'] }}">{{ $cellLevel['symbol'] }}</span>
                                    @if ($row[$column] !== 'No.')
                                        <span class="text-sm text-slate-700">{{ $row[$column] }}</span>
                                    @endif
                                </span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
