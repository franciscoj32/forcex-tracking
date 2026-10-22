<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block">
            <a href="{{ route('usuarios.index') }}" class="hover:underline">Usuarios</a> /
            <a href="{{ route('usuarios.show', $user) }}" class="hover:underline">{{ $user->name }}</a>
        </p>
        <h1>Editar usuario</h1>
    </x-slot>

    <form method="POST" action="{{ route('usuarios.update', $user) }}" data-disable-native-autocomplete="true" class="max-w-3xl">
        @method('PUT')
        @include('users._form')
    </form>
</x-app-layout>
