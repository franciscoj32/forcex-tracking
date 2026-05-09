<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Nuevo embarque</h1>
        <p class="text-sm text-slate-500">Registro completo de la carga internacional.</p>
    </x-slot>

    <div class="fx-card fx-card-body">
        <form method="POST" action="{{ route('embarques.store') }}" data-disable-native-autocomplete="true">
            @include('shipments._form')
        </form>
    </div>
</x-app-layout>
