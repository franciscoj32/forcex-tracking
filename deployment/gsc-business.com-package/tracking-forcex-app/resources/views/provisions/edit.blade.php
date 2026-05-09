<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-slate-900">Editar provisión</h1></x-slot>
    <div class="fx-card fx-card-body">
        <form method="POST" action="{{ route('provisiones.update', $provision) }}" data-disable-native-autocomplete="true">
            @method('PUT')
            @include('provisions._form')
        </form>
    </div>
</x-app-layout>
