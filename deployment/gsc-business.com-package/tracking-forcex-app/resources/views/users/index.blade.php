<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Usuarios</h1>
        <p class="text-sm text-slate-500">Gestion de accesos internos para administracion, operaciones y comercial.</p>
    </x-slot>

    <div class="fx-card">
        <div class="fx-card-header">
            <h2 class="text-lg font-semibold text-slate-900">Listado de usuarios internos</h2>
            <a href="{{ route('usuarios.create') }}" class="fx-btn-primary">Nuevo usuario</a>
        </div>
        <div class="overflow-x-auto">
            <table class="fx-table">
                <thead>
                    <tr>
                        <th class="fx-th">Nombre</th>
                        <th class="fx-th">Usuario</th>
                        <th class="fx-th">Correo</th>
                        <th class="fx-th">Rol</th>
                        <th class="fx-th">Estado</th>
                        <th class="fx-th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($users as $user)
                        <tr>
                            <td class="fx-td font-semibold">{{ $user->name }}</td>
                            <td class="fx-td">{{ $user->username }}</td>
                            <td class="fx-td">{{ $user->email }}</td>
                            <td class="fx-td">{{ ucfirst($user->role) }}</td>
                            <td class="fx-td">{{ ucfirst($user->estado) }}</td>
                            <td class="fx-td"><a href="{{ route('usuarios.show', $user) }}" class="text-cyan-700">Ver</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="fx-td text-center text-slate-500">No hay usuarios internos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">{{ $users->links() }}</div>
    </div>
</x-app-layout>
