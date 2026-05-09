<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Nuevo cliente</h1>
        <p class="text-sm text-slate-500">Registro maestro para asignación de usuarios y embarques.</p>
    </x-slot>

    <div class="fx-card fx-card-body">
        <form method="POST" action="{{ route('clientes.store') }}" data-disable-native-autocomplete="true">
            @include('clients._form')
        </form>
    </div>
</x-app-layout>
