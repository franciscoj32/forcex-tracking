<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Editar usuario</h1>
        <p class="text-sm text-slate-500">{{ $user->name }}</p>
    </x-slot>

    <div class="fx-card fx-card-body">
        <form method="POST" action="{{ route('usuarios.update', $user) }}" data-disable-native-autocomplete="true">
            @method('PUT')
            @include('users._form')
        </form>
    </div>
</x-app-layout>
