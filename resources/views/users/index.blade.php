<x-app-layout>
    <x-slot name="header">
        <h1>Usuarios</h1>
    </x-slot>

    <x-slot name="actions">
        <a href="{{ route('roles.index') }}" class="fx-btn-secondary hidden sm:inline-flex">Permisos por rol</a>
        <a href="{{ route('usuarios.create') }}" class="fx-btn-primary">Nuevo usuario</a>
    </x-slot>

    <div class="overflow-x-auto rounded-md border border-slate-200 bg-white">
        <table class="fx-table">
            <thead>
                <tr>
                    <th class="fx-th">Nombre</th>
                    <th class="fx-th">Usuario</th>
                    <th class="fx-th">Correo</th>
                    <th class="fx-th">Rol</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($users as $user)
                    <tr class="hover:bg-slate-50">
                        <td class="fx-td">
                            <a href="{{ route('usuarios.show', $user) }}" class="font-medium text-slate-900 hover:underline">{{ $user->name }}</a>
                            @if ($user->estado !== 'activo')
                                <span class="fx-badge ml-1 bg-slate-100 text-slate-600">Inactivo</span>
                            @endif
                        </td>
                        <td class="fx-td">{{ $user->username }}</td>
                        <td class="fx-td">{{ $user->email }}</td>
                        <td class="fx-td">{{ $user->role_label }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="fx-td py-10 text-center text-slate-500">Sin usuarios.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($users->hasPages())
        <div class="mt-4">{{ $users->links() }}</div>
    @endif
</x-app-layout>
