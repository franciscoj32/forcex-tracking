{{--
    Campanita de avisos in-app (modulo de Productividad) y avisos de la esquina.
    Sutil: sin sonido, maximo 3 a la vez y se van solos en unos segundos.
--}}
@php
    // Color e icono de cada tipo de aviso.
    $noticeStyles = [
        'tarea' => 'bg-sky-100 text-sky-700',
        'por_vencer' => 'bg-amber-100 text-amber-700',
        'pizarra' => 'bg-violet-100 text-violet-700',
        'cumpleanos' => 'bg-pink-100 text-pink-600',
        'aviso' => 'bg-slate-100 text-slate-600',
    ];
    $noticeBars = [
        'tarea' => 'bg-sky-400',
        'por_vencer' => 'bg-amber-400',
        'pizarra' => 'bg-violet-400',
        'cumpleanos' => 'bg-pink-400',
        'aviso' => 'bg-slate-300',
    ];
    $noticeIcons = collect(['tarea' => 'tasks', 'por_vencer' => 'clock', 'pizarra' => 'megaphone', 'cumpleanos' => 'cake', 'aviso' => 'bell'])
        ->map(fn (string $icon) => \Illuminate\Support\Facades\Blade::render('<x-nav-icon :name="$icon" class="h-4 w-4" />', ['icon' => $icon]))
        ->all();
@endphp

<div
    class="relative"
    x-data="notificationBell({
        userId: @js(auth()->id()),
        count: @js($productivity['unread']),
        recent: @js($productivity['recent']),
        now: @js(now()->toIso8601String()),
        listUrl: @js(route('avisos.index')),
        pollUrl: @js(route('avisos.nuevos')),
        readAllUrl: @js(route('avisos.leidos')),
    })"
    x-on:keydown.escape.window="open = false"
>
    <button type="button" x-on:click="toggle()"
        class="relative flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600/40"
        :class="{ 'bg-slate-100 text-slate-800': open }"
        :aria-expanded="open.toString()" aria-haspopup="true"
        :aria-label="count > 0 ? `Avisos: ${count} sin leer` : 'Avisos'">
        <span class="inline-flex" :class="{ 'fx-bell-ring': ring }"><x-nav-icon name="bell" class="h-5 w-5" /></span>
        <span x-show="count > 0" x-cloak x-text="count > 99 ? '99+' : count"
            class="absolute -right-0.5 -top-0.5 min-w-[1.125rem] rounded-full bg-rose-500 px-1 text-center text-[10px] font-semibold leading-[1.125rem] text-white ring-2 ring-white"></span>
    </button>

    {{-- Panel --}}
    <div x-show="open" x-cloak x-on:click.outside="open = false"
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 top-11 z-40 w-[22rem] max-w-[calc(100vw-2rem)] origin-top-right overflow-hidden rounded-2xl border border-slate-200/80 bg-white/95 shadow-2xl shadow-slate-900/10 backdrop-blur">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <p class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                Avisos
                <span x-show="count > 0" x-text="count" class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-600"></span>
            </p>
            <button type="button" x-show="count > 0" x-on:click="readAll()" class="text-xs font-medium text-brand-700 hover:text-brand-900">Marcar todo como leído</button>
        </div>

        <div class="max-h-[26rem] overflow-y-auto">
            <template x-if="loading && ! loaded">
                <div class="space-y-3 p-4">
                    <div class="h-10 animate-pulse rounded-lg bg-slate-100"></div>
                    <div class="h-10 animate-pulse rounded-lg bg-slate-100"></div>
                </div>
            </template>

            <template x-if="loaded && items.length === 0">
                <div class="px-6 py-10 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-accent-50 text-accent-600"><x-nav-icon name="check-circle" class="h-6 w-6" /></span>
                    <p class="mt-3 text-sm font-medium text-slate-900">Estás al día</p>
                    <p class="mt-1 text-xs text-slate-500">Aquí verás las tareas que te asignen, los recordatorios y los anuncios de la pizarra.</p>
                </div>
            </template>

            <ul class="divide-y divide-slate-100">
                <template x-for="item in items" :key="item.id">
                    <li>
                        <a :href="item.url" class="flex gap-3 px-4 py-3 transition hover:bg-slate-50" :class="item.leido ? '' : 'bg-brand-50/50'">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                :class="@js($noticeStyles)[item.tipo] ?? @js($noticeStyles['aviso'])"
                                x-html="@js($noticeIcons)[item.tipo] ?? @js($noticeIcons['aviso'])"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm text-slate-900" :class="item.leido ? '' : 'font-semibold'" x-text="item.titulo"></span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500" x-text="item.texto"></span>
                            </span>
                            <span class="flex shrink-0 flex-col items-end gap-1.5">
                                <span class="text-[11px] text-slate-400" x-text="item.hace"></span>
                                <span x-show="! item.leido" class="h-2 w-2 rounded-full bg-brand-600"></span>
                            </span>
                        </a>
                    </li>
                </template>
            </ul>
        </div>

        <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/70 px-4 py-2.5 text-xs">
            <a href="{{ route('tareas.index') }}" class="font-medium text-slate-600 hover:text-slate-900">Mis tareas</a>
            <a href="{{ route('pizarra.index') }}" class="font-medium text-slate-600 hover:text-slate-900">Pizarra</a>
        </div>
    </div>

    {{-- Avisos de la esquina --}}
    <template x-teleport="body">
        <div class="pointer-events-none fixed right-4 top-16 z-[60] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2" aria-live="polite">
            <template x-for="toast in toasts" :key="toast.key">
                <div x-show="toast.show"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-6" x-transition:enter-end="opacity-100 translate-x-0"
                    x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-6"
                    x-on:mouseenter="pause(toast)" x-on:mouseleave="resume(toast)"
                    class="fx-toast pointer-events-auto relative overflow-hidden rounded-xl border border-slate-200/80 bg-white/95 shadow-lg shadow-slate-900/10 backdrop-blur">
                    <a :href="toast.url" class="flex gap-3 p-3 pr-9">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                            :class="@js($noticeStyles)[toast.tipo] ?? @js($noticeStyles['aviso'])"
                            x-html="@js($noticeIcons)[toast.tipo] ?? @js($noticeIcons['aviso'])"></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900" x-text="toast.titulo"></span>
                            <span class="mt-0.5 block text-xs leading-snug text-slate-600" x-text="toast.texto"></span>
                        </span>
                    </a>
                    <button type="button" x-on:click="dismiss(toast)" class="absolute right-2 top-2 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Cerrar aviso">
                        <x-nav-icon name="close" class="h-3.5 w-3.5" />
                    </button>
                    <span class="fx-toast-progress absolute inset-x-0 bottom-0 h-0.5" :class="@js($noticeBars)[toast.tipo] ?? @js($noticeBars['aviso'])"></span>
                </div>
            </template>
        </div>
    </template>
</div>
