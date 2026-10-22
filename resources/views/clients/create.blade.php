<x-app-layout>
    <x-slot name="header">
        <h1>Nuevo cliente</h1>
    </x-slot>

    <form method="POST" action="{{ route('clientes.store') }}" data-disable-native-autocomplete="true" class="max-w-5xl">
        @include('clients._form')
    </form>
</x-app-layout>
