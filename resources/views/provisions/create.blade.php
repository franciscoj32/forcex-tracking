<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-slate-900">Nueva provisión</h1></x-slot>
    <div class="fx-card fx-card-body">
        <form method="POST" action="{{ route('provisiones.store') }}" data-disable-native-autocomplete="true">
            @include('provisions._form')
        </form>
    </div>
</x-app-layout>
