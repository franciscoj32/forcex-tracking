<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block"><a href="{{ route('usuarios.index') }}" class="hover:underline">Usuarios</a></p>
        <h1>{{ $user->name }}</h1>
    </x-slot>

    <x-slot name="actions">
        <a href="{{ route('usuarios.edit', $user) }}" class="fx-btn-secondary">Editar</a>
    </x-slot>

    <section class="fx-card max-w-2xl">
        <dl class="divide-y divide-slate-100 px-4 py-1">
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Usuario</dt><dd class="text-slate-900">{{ $user->username }}</dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Correo</dt><dd class="text-slate-900">{{ $user->email }}</dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Rol</dt><dd class="text-slate-900">{{ $user->role_label }}</dd></div>
            <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Estado</dt><dd class="text-slate-900">{{ ucfirst($user->estado) }}</dd></div>
        </dl>
        @if (auth()->id() !== $user->id)
            <div class="border-t border-slate-200 px-4 py-3">
                <form method="POST" action="{{ route('usuarios.destroy', $user) }}" onsubmit="return confirm('¿Eliminar este usuario? Si solo deja la empresa, mejor márcalo como inactivo.')">
                    @csrf
                    @method('DELETE')
                    <button class="text-sm font-medium text-rose-700 hover:underline" type="submit">Eliminar usuario</button>
                </form>
            </div>
        @endif
    </section>
</x-app-layout>
