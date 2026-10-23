{{-- Saludo de cumpleanos con un confeti breve. Una vez al dia en cada equipo. --}}
<div x-data="birthdayGreeting({ userId: @js(auth()->id()), day: @js(today()->toDateString()) })">
    <template x-teleport="body">
        <div x-show="show" x-cloak
            x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 -translate-y-6" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 -translate-y-4"
            class="pointer-events-none fixed inset-x-0 top-16 z-[65] flex justify-center px-4">
            <div class="pointer-events-auto relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl shadow-pink-900/10 ring-1 ring-pink-100" role="dialog" aria-label="Feliz cumpleaños">
                <div class="h-1.5 bg-gradient-to-r from-pink-500 via-amber-400 to-violet-500"></div>
                <div class="flex gap-4 p-5">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-pink-100 to-amber-100 text-2xl" aria-hidden="true">🎂</span>
                    <div class="min-w-0">
                        <p class="text-base font-semibold text-slate-900">¡Feliz cumpleaños, {{ auth()->user()->firstName() }}!</p>
                        <p class="mt-1 text-sm leading-relaxed text-slate-600">Todo el equipo de {{ \App\Models\AppSetting::current()->company_name }} te desea un gran día. Tu saludo ya está en la pizarra.</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3">
                    <a href="{{ route('pizarra.index') }}" x-on:click="close()" class="text-sm font-medium text-slate-600 hover:text-slate-900">Ver la pizarra</a>
                    <button type="button" x-on:click="close()" class="inline-flex h-9 items-center rounded-lg bg-gradient-to-r from-pink-500 to-amber-500 px-4 text-sm font-semibold text-white shadow-sm transition hover:brightness-105">¡Gracias!</button>
                </div>
                <button type="button" x-on:click="close()" class="absolute right-3 top-4 rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Cerrar">
                    <x-nav-icon name="close" class="h-4 w-4" />
                </button>
            </div>
        </div>
    </template>
</div>
