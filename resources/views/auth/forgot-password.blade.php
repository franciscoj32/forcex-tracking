<x-guest-layout>
    <h1 class="text-xl font-semibold">Recuperar contraseña</h1>
    <p class="mt-1 text-slate-600">Escribe tu correo y te enviaremos un enlace para crear una nueva.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-5 space-y-4">
        @csrf
        <div>
            <label class="fx-label" for="email">Correo</label>
            <input class="fx-input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>
        <button type="submit" class="fx-btn-primary w-full">Enviar enlace</button>
        <a href="{{ route('login') }}" class="fx-btn-link-primary block text-center">Volver a iniciar sesión</a>
    </form>
</x-guest-layout>
