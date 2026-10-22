<x-app-layout>
    <x-slot name="header">
        <h1>Nuevo embarque</h1>
    </x-slot>

    <form method="POST" action="{{ route('embarques.store') }}" data-disable-native-autocomplete="true" class="max-w-6xl">
        @include('shipments._form')
    </form>
</x-app-layout>
