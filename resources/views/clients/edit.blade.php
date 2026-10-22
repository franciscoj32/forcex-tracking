<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block">
            <a href="{{ route('clientes.index') }}" class="hover:underline">Clientes</a> /
            <a href="{{ route('clientes.show', $client) }}" class="hover:underline">{{ $client->razon_social }}</a>
        </p>
        <h1>Editar cliente</h1>
    </x-slot>

    <form method="POST" action="{{ route('clientes.update', $client) }}" data-disable-native-autocomplete="true" class="max-w-5xl">
        @method('PUT')
        @include('clients._form')
    </form>
</x-app-layout>
