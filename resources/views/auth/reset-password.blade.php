<x-guest-layout>
    <h1 class="text-xl font-semibold">Nueva contraseña</h1>

    <form method="POST" action="{{ route('password.store') }}" class="mt-5 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label class="fx-label" for="email">Correo</label>
            <input class="fx-input" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>
        <div>
            <label class="fx-label" for="password">Nueva contraseña</label>
            <input class="fx-input" id="password" type="password" name="password" required autocomplete="new-password">
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>
        <div>
            <label class="fx-label" for="password_confirmation">Repite la nueva contraseña</label>
            <input class="fx-input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>
        <button type="submit" class="fx-btn-primary w-full">Guardar contraseña</button>
    </form>
</x-guest-layout>
