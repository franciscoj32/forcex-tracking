<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block">
            <a href="{{ route('embarques.index') }}" class="hover:underline">Embarques</a> /
            <a href="{{ route('embarques.show', $shipment) }}" class="hover:underline">{{ $shipment->nro_orden_trabajo }}</a>
        </p>
        <h1>Editar {{ $shipment->nro_orden_trabajo }}</h1>
    </x-slot>

    <form method="POST" action="{{ route('embarques.update', $shipment) }}" data-disable-native-autocomplete="true" class="max-w-6xl">
        @method('PUT')
        @include('shipments._form')
    </form>
</x-app-layout>
