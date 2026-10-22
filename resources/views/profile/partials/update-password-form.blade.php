<section class="fx-card">
    <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Cambiar contraseña</h2>

    <form method="post" action="{{ route('password.update') }}" class="space-y-4 p-4">
        @csrf
        @method('put')

        <div>
            <label class="fx-label" for="update_password_current_password">Contraseña actual</label>
            <input class="fx-input" id="update_password_current_password" name="current_password" type="password" autocomplete="current-password">
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="fx-label" for="update_password_password">Nueva contraseña</label>
                <input class="fx-input" id="update_password_password" name="password" type="password" autocomplete="new-password">
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1" />
            </div>
            <div>
                <label class="fx-label" for="update_password_password_confirmation">Repite la nueva contraseña</label>
                <input class="fx-input" id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1" />
            </div>
        </div>

        <button class="fx-btn-primary" type="submit">Cambiar contraseña</button>
    </form>
</section>
