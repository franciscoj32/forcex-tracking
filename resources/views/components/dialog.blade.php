{{--
    Ventana emergente reutilizable.
    Abrir desde cualquier boton: x-data x-on:click="$dispatch('open-dialog', 'nombre')"
    Dentro del contenido, "open = false" la cierra.
    Se cierra con Esc o clic en el fondo (no con clics en calendarios o listas que
    se abren fuera de la ventana).
--}}
@props([
    'name',
    'title',
    'show' => false,
    'maxWidth' => 'max-w-2xl',
])

<div
    x-data="{ open: @js((bool) $show) }"
    x-on:open-dialog.window="if ($event.detail === @js($name)) open = true"
    x-on:keydown.escape.window="open = false"
>
    <template x-teleport="body">
        {{-- El fondo es el que se desplaza: asi las listas desplegables no quedan recortadas. --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-slate-900/40" x-on:click="open = false"></div>
            <div class="relative flex items-end justify-center sm:items-center sm:p-4" style="min-height: 100%" x-on:click.self="open = false">
                <section
                    class="relative w-full {{ $maxWidth }} rounded-t-md bg-white shadow-lg sm:rounded-md"
                    role="dialog"
                    aria-modal="true"
                    aria-label="{{ $title }}"
                >
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                        <h3 class="fx-section-title">{{ $title }}</h3>
                        <button type="button" x-on:click="open = false" class="rounded p-1 text-slate-400 hover:text-slate-700" aria-label="Cerrar">
                            <x-nav-icon name="close" class="h-5 w-5" />
                        </button>
                    </div>
                    {{ $slot }}
                </section>
            </div>
        </div>
    </template>
</div>
