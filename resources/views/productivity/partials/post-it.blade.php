{{--
    Post-it flotante: mis tareas de hoy (y las atrasadas). Se arrastra a cualquier
    lugar, se minimiza a un boton y recuerda posicion y estado en este equipo.
    En el celular empieza minimizado para no tapar la pantalla.
--}}
<div
    x-data="stickyNote({
        userId: @js(auth()->id()),
        tasks: @js($productivity['postIts']),
        storeUrl: @js(route('tareas.store')),
    })"
    x-on:pointermove.window="onDrag($event)"
    x-on:pointerup.window="endDrag()"
    x-on:task-created.window="tasks.push($event.detail)"
>
    <div x-ref="box" class="fixed bottom-5 right-5 z-30 select-none print:hidden" :style="position()">
        {{-- Minimizado --}}
        <button type="button" x-show="minimized"
            x-on:pointerdown="startDrag($event)" x-on:click="toggleMinimized()"
            class="fx-postit relative flex h-12 w-12 items-center justify-center rounded-2xl text-amber-800 shadow-lg shadow-amber-900/15 ring-1 ring-amber-300/70 transition hover:-translate-y-0.5 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500"
            :class="dragging ? 'cursor-grabbing' : 'cursor-pointer'"
            aria-label="Abrir mis post-its" title="Mis post-its de hoy">
            <x-nav-icon name="note" class="h-6 w-6" />
            <span x-show="pending > 0" x-cloak x-text="pending"
                class="absolute -right-1.5 -top-1.5 min-w-[1.25rem] rounded-full bg-amber-500 px-1 text-center text-[11px] font-bold leading-5 text-white ring-2 ring-white"></span>
        </button>

        {{-- Abierto --}}
        <section x-show="! minimized" x-cloak
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            class="fx-postit flex max-h-[min(30rem,calc(100vh-6rem))] w-[19rem] flex-col overflow-hidden rounded-2xl shadow-xl shadow-amber-900/15 ring-1 ring-amber-300/70"
            aria-label="Mis post-its de hoy">
            <header x-on:pointerdown="startDrag($event)" style="touch-action: none"
                class="flex items-center gap-2 border-b border-amber-200/80 px-3 py-2"
                :class="mobile ? '' : (dragging ? 'cursor-grabbing' : 'cursor-grab')">
                <x-nav-icon name="grip" class="hidden h-4 w-4 text-amber-400 sm:block" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-amber-950">Mis post-its</p>
                    <p class="text-[11px] text-amber-800/70">Hoy, {{ now()->locale('es')->translatedFormat('l j \d\e F') }}</p>
                </div>
                <span x-show="pending > 0" x-text="pending" class="rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-semibold text-amber-800"></span>
                <a href="{{ route('tareas.index') }}" data-no-drag class="rounded-lg p-1.5 text-amber-700 transition hover:bg-amber-200/60 hover:text-amber-900" title="Abrir Tareas" aria-label="Abrir Tareas">
                    <x-nav-icon name="external" class="h-4 w-4" />
                </a>
                <button type="button" data-no-drag x-on:click="toggleMinimized()" class="rounded-lg p-1.5 text-amber-700 transition hover:bg-amber-200/60 hover:text-amber-900" title="Minimizar" aria-label="Minimizar">
                    <x-nav-icon name="minus" class="h-4 w-4" />
                </button>
            </header>

            <ul class="flex-1 space-y-0.5 overflow-y-auto px-2 py-2">
                <template x-if="tasks.length === 0">
                    <li class="px-2 py-5 text-center text-xs leading-relaxed text-amber-800/70">Nada pendiente para hoy.<br>Escribe abajo y presiona Enter.</li>
                </template>
                <template x-for="task in tasks" :key="task.id">
                    <li class="group flex items-start gap-2 rounded-lg px-1.5 py-1.5 transition hover:bg-amber-100/70">
                        <button type="button" data-no-drag x-on:click="toggle(task)"
                            class="mt-0.5 flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full border-2 transition"
                            :class="task.completada ? 'border-amber-600 bg-amber-500 text-white' : 'border-amber-400 bg-white/70 hover:border-amber-600'"
                            :aria-label="task.completada ? 'Marcar como pendiente' : 'Marcar como hecha'">
                            <x-nav-icon name="check" class="h-3 w-3" x-show="task.completada" />
                        </button>
                        <div class="min-w-0 flex-1">
                            <p class="break-words text-sm leading-snug transition" :class="task.completada ? 'text-amber-900/40 line-through' : 'text-amber-950'" x-text="task.titulo"></p>
                            <p class="mt-0.5 flex flex-wrap gap-x-2 text-[11px] text-amber-800/70" x-show="task.hora || task.vencida || task.ro || task.de">
                                <span x-show="task.hora || task.vencida" :class="task.vencida && ! task.completada ? 'font-semibold text-rose-600' : ''" x-text="task.vencida ? 'Atrasada · ' + task.cuando : task.cuando"></span>
                                <a x-show="task.ro" :href="task.ro_url" data-no-drag class="font-medium underline-offset-2 hover:underline" x-text="'RO ' + task.ro"></a>
                                <span x-show="task.de" x-text="'de ' + task.de"></span>
                            </p>
                        </div>
                    </li>
                </template>
            </ul>

            <form x-on:submit.prevent="add()" class="border-t border-amber-200/80 p-2" data-no-drag>
                <div class="flex items-center gap-1.5">
                    <label for="postit-draft" class="sr-only">Nueva nota para hoy</label>
                    <input id="postit-draft" x-ref="draft" x-model="draft" maxlength="160" autocomplete="off" placeholder="Nueva nota para hoy…"
                        class="h-9 w-full rounded-lg border border-amber-200 bg-white/70 px-3 text-sm text-amber-950 placeholder:text-amber-700/50 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/30">
                    <button type="submit" :disabled="saving || ! draft.trim()" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white transition hover:bg-amber-600 disabled:opacity-40" aria-label="Agregar">
                        <x-nav-icon name="plus" class="h-4 w-4" />
                    </button>
                </div>
                <p x-show="error" x-text="error" class="mt-1 px-1 text-[11px] text-rose-600"></p>
                <button type="button" x-show="done > 0" x-on:click="clearDone()" class="mt-1 px-1 text-[11px] font-medium text-amber-800/70 hover:text-amber-950" x-text="`Quitar hechas (${done})`"></button>
            </form>
        </section>
    </div>
</div>
