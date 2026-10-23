<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block"><a href="{{ route('cotizaciones.index') }}" class="hover:underline">Cotizaciones</a> / {{ $quotation->numero }}</p>
        <h1>Editar cotización {{ $quotation->numero }}</h1>
    </x-slot>

    <form method="POST" action="{{ route('cotizaciones.update', $quotation) }}" data-disable-native-autocomplete="true">
        @method('PUT')
        @include('quotations._form')
    </form>
</x-app-layout>
