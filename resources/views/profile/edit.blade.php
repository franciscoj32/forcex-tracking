<x-app-layout>
    <x-slot name="header">
        <h1>Mi perfil</h1>
    </x-slot>

    <div class="max-w-2xl space-y-4">
        @include('profile.partials.update-profile-information-form')
        @include('profile.partials.update-password-form')
        @include('profile.partials.delete-user-form')
    </div>
</x-app-layout>
