<x-app-layout>
    <x-slot name="header"><h1>Editar factura {{ $invoice->numero }}</h1></x-slot>

    <form method="POST" action="{{ route('facturacion.update', $invoice) }}" enctype="multipart/form-data" data-disable-native-autocomplete="true" class="max-w-4xl">
        @method('PUT')
        @include('invoices._form')
    </form>
</x-app-layout>
