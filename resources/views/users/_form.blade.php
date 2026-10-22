@csrf

@php
    $roleOptions = collect($roles)
        ->map(fn ($role) => ['value' => $role, 'label' => \App\Models\User::roleLabel($role)]);
    $statusOptions = collect(['activo', 'inactivo'])
        ->map(fn ($estado) => ['value' => $estado, 'label' => ucfirst($estado)]);
@endphp

<div class="fx-card grid gap-4 p-4 sm:grid-cols-2">
    <div>
        <label class="fx-label" for="name">Nombre</label>
        <input class="fx-input" id="name" name="name" value="{{ old('name', $user->name ?? '') }}" required>
    </div>
    <div>
        <label class="fx-label" for="username">Usuario</label>
        <input class="fx-input" id="username" name="username" value="{{ old('username', $user->username ?? '') }}" required>
    </div>
    <div>
        <label class="fx-label" for="email">Correo</label>
        <input class="fx-input" id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" required>
    </div>
    <div>
        <div class="flex items-center justify-between gap-3">
            <label class="fx-label" for="password">Contraseña {{ isset($user) ? '(vacío = no cambia)' : '' }}</label>
            <div class="flex items-center gap-3">
                <button type="button" id="generate-password" class="fx-btn-link-primary text-xs">Generar</button>
                <button type="button" id="copy-password" class="fx-btn-link-primary text-xs">Copiar</button>
            </div>
        </div>
        <div class="relative mt-1">
            <input class="fx-input !mt-0 pr-12" id="password" name="password" type="password" {{ isset($user) ? '' : 'required' }}>
            <button
                type="button"
                id="toggle-user-password"
                class="absolute inset-y-0 right-2 my-auto flex h-8 w-8 items-center justify-center rounded text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                aria-label="Mostrar contraseña"
                aria-pressed="false"
            >
                <svg id="user-icon-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <svg id="user-icon-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="hidden h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.477 10.488A3 3 0 0 0 12 15a3 3 0 0 0 2.533-1.392" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A9.953 9.953 0 0 1 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639a10.01 10.01 0 0 1-4.043 5.135M6.228 6.228A9.956 9.956 0 0 0 2.037 11.68a1.012 1.012 0 0 0 0 .639C3.423 16.49 7.36 19.5 12 19.5a9.95 9.95 0 0 0 5.272-1.508" />
                </svg>
            </button>
        </div>
        <p id="password-copy-status" class="mt-1 text-xs text-slate-500"></p>
    </div>
    <div>
        <x-form-combobox
            id="role"
            name="role"
            label="Rol"
            :options="$roleOptions"
            :value="old('role', $user->role ?? 'operaciones')"
            placeholder="Seleccionar rol"
        />
    </div>
    <div>
        <x-form-combobox
            id="estado"
            name="estado"
            label="Estado"
            :options="$statusOptions"
            :value="old('estado', $user->estado ?? 'activo')"
            placeholder="Seleccionar estado"
        />
    </div>
</div>

<div class="mt-4 flex gap-2">
    <button class="fx-btn-primary" type="submit">Guardar</button>
    <a href="{{ route('usuarios.index') }}" class="fx-btn-secondary">Cancelar</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const passwordInput = document.getElementById('password');
        const generateButton = document.getElementById('generate-password');
        const copyButton = document.getElementById('copy-password');
        const statusText = document.getElementById('password-copy-status');
        const toggleButton = document.getElementById('toggle-user-password');
        const eyeIcon = document.getElementById('user-icon-eye');
        const eyeOffIcon = document.getElementById('user-icon-eye-off');

        if (!passwordInput) {
            return;
        }

        const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*_-';

        const setStatus = (message, success = true) => {
            if (!statusText) {
                return;
            }

            statusText.textContent = message;
            statusText.className = success ? 'mt-1 text-xs text-slate-600' : 'mt-1 text-xs text-rose-600';
        };

        generateButton?.addEventListener('click', () => {
            let generated = '';

            for (let index = 0; index < 14; index += 1) {
                generated += alphabet.charAt(Math.floor(Math.random() * alphabet.length));
            }

            passwordInput.value = generated;
            passwordInput.type = 'text';
            toggleButton?.setAttribute('aria-label', 'Ocultar contraseña');
            toggleButton?.setAttribute('aria-pressed', 'true');
            eyeIcon?.classList.add('hidden');
            eyeOffIcon?.classList.remove('hidden');
            setStatus('Contraseña generada.');
        });

        copyButton?.addEventListener('click', async () => {
            if (!passwordInput.value) {
                setStatus('Primero genera o escribe una contraseña.', false);
                return;
            }

            try {
                await navigator.clipboard.writeText(passwordInput.value);
                setStatus('Contraseña copiada al portapapeles.');
            } catch (error) {
                setStatus('No se pudo copiar automáticamente.', false);
            }
        });

        toggleButton?.addEventListener('click', () => {
            const showingPassword = passwordInput.type === 'text';

            passwordInput.type = showingPassword ? 'password' : 'text';
            toggleButton.setAttribute('aria-label', showingPassword ? 'Mostrar contraseña' : 'Ocultar contraseña');
            toggleButton.setAttribute('aria-pressed', showingPassword ? 'false' : 'true');
            eyeIcon?.classList.toggle('hidden', !showingPassword);
            eyeOffIcon?.classList.toggle('hidden', showingPassword);
        });
    });
</script>
