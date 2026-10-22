<section class="fx-card">
    <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Mis datos</h2>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-4 p-4">
        @csrf
        @method('patch')

        <div>
            <label class="fx-label" for="name">Nombre</label>
            <input class="fx-input" id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name">
            <x-input-error class="mt-1" :messages="$errors->get('name')" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="fx-label" for="username">Usuario</label>
                <input class="fx-input" id="username" name="username" type="text" value="{{ old('username', $user->username) }}" required autocomplete="username">
                <x-input-error class="mt-1" :messages="$errors->get('username')" />
            </div>
            <div>
                <label class="fx-label" for="email">Correo</label>
                <input class="fx-input" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email">
                <x-input-error class="mt-1" :messages="$errors->get('email')" />
            </div>
        </div>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <p class="text-sm text-amber-800">
                Tu correo no está verificado.
                <button form="send-verification" class="fx-btn-link-primary">Reenviar el enlace de verificación</button>
            </p>
        @endif

        <p class="text-sm text-slate-500">Rol: {{ \App\Models\User::roleLabel($user->role) }}. Solo el Administrador puede cambiarlo.</p>

        <button class="fx-btn-primary" type="submit">Guardar</button>
    </form>
</section>
