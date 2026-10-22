<x-app-layout>
    <x-slot name="header"><h1>Registrar factura</h1></x-slot>

    <form method="POST" action="{{ route('facturacion.store') }}" enctype="multipart/form-data" data-disable-native-autocomplete="true" class="max-w-4xl">
        @include('invoices._form')
    </form>
</x-app-layout>
