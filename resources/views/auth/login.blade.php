<x-guest-layout>
    <h1 class="mb-6 text-xl font-semibold">Iniciar sesión</h1>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="login" class="fx-label">Usuario o correo</label>
            <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" class="fx-input">
            <x-input-error :messages="$errors->get('login')" class="mt-2" />
        </div>

        <div>
            <label for="password" class="fx-label">Contraseña</label>
            <div class="relative mt-1">
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="fx-input !mt-0 pr-12"
                >
                <button
                    type="button"
                    id="toggle-password"
                    class="absolute inset-y-0 right-2 my-auto flex h-8 w-8 items-center justify-center rounded text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Mostrar contraseña"
                    aria-pressed="false"
                >
                    <svg id="icon-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <svg id="icon-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="hidden h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.477 10.488A3 3 0 0 0 12 15a3 3 0 0 0 2.533-1.392" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A9.953 9.953 0 0 1 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639a10.01 10.01 0 0 1-4.043 5.135M6.228 6.228A9.956 9.956 0 0 0 2.037 11.68a1.012 1.012 0 0 0 0 .639C3.423 16.49 7.36 19.5 12 19.5a9.95 9.95 0 0 0 5.272-1.508" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/30" name="remember">
                <span>Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a class="fx-btn-link-primary" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <button type="submit" class="fx-btn-primary w-full">
            Ingresar
        </button>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('toggle-password');
            const eyeIcon = document.getElementById('icon-eye');
            const eyeOffIcon = document.getElementById('icon-eye-off');

            if (!passwordInput || !toggleButton) {
                return;
            }

            toggleButton.addEventListener('click', () => {
                const showingPassword = passwordInput.type === 'text';

                passwordInput.type = showingPassword ? 'password' : 'text';
                toggleButton.setAttribute('aria-label', showingPassword ? 'Mostrar contraseña' : 'Ocultar contraseña');
                toggleButton.setAttribute('aria-pressed', showingPassword ? 'false' : 'true');
                eyeIcon.classList.toggle('hidden', !showingPassword);
                eyeOffIcon.classList.toggle('hidden', showingPassword);
            });
        });
    </script>
</x-guest-layout>
