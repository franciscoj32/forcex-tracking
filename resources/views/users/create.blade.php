<x-app-layout>
    <x-slot name="header">
        <h1>Nuevo usuario</h1>
    </x-slot>

    <x-slot name="actions">
        <a href="{{ route('roles.index') }}" class="fx-btn-secondary hidden sm:inline-flex">Permisos por rol</a>
    </x-slot>

    <form method="POST" action="{{ route('usuarios.store') }}" data-disable-native-autocomplete="true" class="max-w-3xl">
        @include('users._form')
    </form>
</x-app-layout>
