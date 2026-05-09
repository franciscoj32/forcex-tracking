<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Editar embarque</h1>
        <p class="text-sm text-slate-500">{{ $shipment->nro_orden_trabajo }}</p>
    </x-slot>

    <div class="fx-card fx-card-body">
        <form method="POST" action="{{ route('embarques.update', $shipment) }}" data-disable-native-autocomplete="true">
            @method('PUT')
            @include('shipments._form')
        </form>
    </div>
</x-app-layout>
