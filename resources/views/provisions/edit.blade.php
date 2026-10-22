<x-app-layout>
    <x-slot name="header"><h1>Editar costo de proveedor</h1></x-slot>

    <form method="POST" action="{{ route('provisiones.update', $provision) }}" data-disable-native-autocomplete="true" class="max-w-4xl">
        @method('PUT')
        @include('provisions._form')
    </form>
</x-app-layout>
