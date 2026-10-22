<x-guest-layout>
    <h1 class="text-xl font-semibold">Confirma tu contraseña</h1>
    <p class="mt-1 text-slate-600">Por seguridad, escríbela de nuevo para continuar.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-5 space-y-4">
        @csrf
        <div>
            <label class="fx-label" for="password">Contraseña</label>
            <input class="fx-input" id="password" type="password" name="password" required autocomplete="current-password">
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>
        <button type="submit" class="fx-btn-primary w-full">Confirmar</button>
    </form>
</x-guest-layout>
