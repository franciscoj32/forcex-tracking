<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Editar cliente</h1>
        <p class="text-sm text-slate-500">{{ $client->razon_social }}</p>
    </x-slot>

    <div class="fx-card fx-card-body">
        <form method="POST" action="{{ route('clientes.update', $client) }}" data-disable-native-autocomplete="true">
            @method('PUT')
            @include('clients._form')
        </form>
    </div>
</x-app-layout>
