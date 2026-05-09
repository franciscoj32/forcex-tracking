<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $user->name }}</h1>
        <p class="text-sm text-slate-500">Perfil interno de acceso.</p>
    </x-slot>

    <div class="fx-card fx-card-body">
        <dl class="grid gap-4 md:grid-cols-2">
            <div><dt class="text-sm text-slate-500">Usuario</dt><dd class="font-medium">{{ $user->username }}</dd></div>
            <div><dt class="text-sm text-slate-500">Correo</dt><dd class="font-medium">{{ $user->email }}</dd></div>
            <div><dt class="text-sm text-slate-500">Rol</dt><dd class="font-medium">{{ ucfirst($user->role) }}</dd></div>
            <div><dt class="text-sm text-slate-500">Estado</dt><dd class="font-medium">{{ ucfirst($user->estado) }}</dd></div>
        </dl>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('usuarios.edit', $user) }}" class="fx-btn-primary">Editar</a>
            @if (auth()->id() !== $user->id)
                <form method="POST" action="{{ route('usuarios.destroy', $user) }}" onsubmit="return confirm('Eliminar usuario interno?')">
                    @csrf
                    @method('DELETE')
                    <button class="fx-btn-danger" type="submit">Eliminar</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
