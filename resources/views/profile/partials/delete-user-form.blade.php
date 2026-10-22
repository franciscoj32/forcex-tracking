<details class="fx-card group" @if ($errors->userDeletion->isNotEmpty()) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3">
        <span class="text-sm font-medium text-slate-600">Eliminar mi cuenta</span>
        <span class="text-sm text-slate-400 group-open:hidden">Mostrar</span>
        <span class="hidden text-sm text-slate-400 group-open:inline">Ocultar</span>
    </summary>

    <form method="post" action="{{ route('profile.destroy') }}" class="space-y-3 border-t border-slate-200 p-4"
          onsubmit="return confirm('¿Eliminar tu cuenta definitivamente? No se puede deshacer.')">
        @csrf
        @method('delete')

        <p class="text-sm text-slate-700">Tu cuenta se borra de forma definitiva. Si dejas la empresa, pide al Administrador que la marque como inactiva.</p>

        <div>
            <label class="fx-label" for="password">Escribe tu contraseña para confirmar</label>
            <input class="fx-input max-w-sm" id="password" name="password" type="password" autocomplete="current-password">
            <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-1" />
        </div>

        <button type="submit" class="fx-btn-danger">Eliminar mi cuenta</button>
    </form>
</details>
