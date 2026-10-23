<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block"><a href="{{ route('cotizaciones.index') }}" class="hover:underline">Cotizaciones</a> / Nueva</p>
        <h1>Nueva cotización</h1>
    </x-slot>

    <form method="POST" action="{{ route('cotizaciones.store') }}" data-disable-native-autocomplete="true">
        @include('quotations._form')
    </form>
</x-app-layout>
