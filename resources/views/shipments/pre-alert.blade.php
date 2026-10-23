{{--
    Pre-alerta de llegada en pagina completa. Lo normal es abrirla en la ventana
    emergente (Inicio o ficha); esta pagina queda para enlaces directos y para
    mostrar los errores de un envio hecho desde el Inicio.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0">
            <h1>Pre-alerta de llegada</h1>
            <p class="text-sm font-normal text-slate-500">
                <a href="{{ route('embarques.show', $shipment) }}" class="hover:underline">{{ $shipment->routing_order }}</a>
                · {{ $shipment->client->razon_social }}
            </p>
        </div>
    </x-slot>

    <section class="fx-card">
        @include('shipments.partials.pre-alert-form')
    </section>
</x-app-layout>
