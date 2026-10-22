<x-app-layout>
    <x-slot name="header"><h1>Registrar costo de proveedor</h1></x-slot>

    <form method="POST" action="{{ route('provisiones.store') }}" data-disable-native-autocomplete="true" class="max-w-4xl">
        @include('provisions._form')
    </form>
</x-app-layout>
