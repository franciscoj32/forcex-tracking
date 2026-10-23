{{-- Fila de la lista (checklist). $task, $payload (para editar), $highlight, $me, $now --}}
@php
    $overdue = $task->isOverdue($now);
    $dueSoon = ! $overdue && $task->isDueSoon($now);
    $mine = (int) $task->asignado_a_id === (int) $me->id;
@endphp
<li id="tarea-{{ $task->id }}"
    x-data="{
        done: @js($task->isCompleted()),
        busy: false,
        async toggle() {
            if (this.busy) return;
            this.busy = true;
            this.done = ! this.done;
            try {
                const { data } = await window.axios.post(@js(route('tareas.completar', $task)), {}, { headers: { Accept: 'application/json' } });
                this.done = data.completada;
            } catch (error) {
                this.done = ! this.done;
            } finally {
                this.busy = false;
            }
        },
    }"
    @if ($highlight === $task->id) x-init="$el.scrollIntoView({ block: 'center' })" @endif
    @class([
        'group flex items-start gap-3 px-4 py-3 transition-colors hover:bg-slate-50',
        'bg-amber-50/80 ring-1 ring-inset ring-amber-300' => $highlight === $task->id,
    ])>
    @can('complete', $task)
        <button type="button" x-on:click="toggle()"
            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition"
            :class="done ? 'border-accent-600 bg-accent-600 text-white' : 'border-slate-300 bg-white hover:border-brand-500'"
            :aria-label="done ? 'Marcar como pendiente' : 'Marcar como hecha'">
            <x-nav-icon name="check" class="h-3 w-3" x-show="done" />
        </button>
    @else
        <span class="mt-0.5 h-5 w-5 shrink-0 rounded-full border-2 border-slate-200" aria-hidden="true"></span>
    @endcan

    <div class="min-w-0 flex-1">
        <p class="break-words font-medium transition" :class="done ? 'text-slate-400 line-through' : 'text-slate-900'">{{ $task->titulo }}</p>
        @if ($task->descripcion)
            <p class="mt-0.5 line-clamp-2 text-xs text-slate-500" :class="done && 'line-through'">{{ $task->descripcion }}</p>
        @endif
        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs">
            <span @class([
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium',
                'bg-rose-50 text-rose-700' => $overdue,
                'bg-amber-50 text-amber-700' => $dueSoon,
                'bg-amber-100/70 text-amber-800' => ! $overdue && ! $dueSoon && $task->isPostIt(),
                'bg-slate-100 text-slate-600' => ! $overdue && ! $dueSoon && ! $task->isPostIt(),
            ])>
                <x-nav-icon :name="$task->isPostIt() ? 'note' : 'clock'" class="h-3 w-3" />
                {{ $overdue ? 'Atrasada · ' : '' }}{{ $task->whenLabel($now) }}{{ $task->isPostIt() && ! $overdue ? ' · post-it' : '' }}
            </span>
            @if ($task->shipment)
                <a href="{{ route('embarques.show', $task->shipment_id) }}" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 font-medium text-brand-700 hover:bg-brand-100">
                    <x-nav-icon name="shipments" class="h-3 w-3" /> RO {{ $task->shipment->routing_order }}
                </a>
            @endif
            @if (! $mine)
                <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-sky-700"><x-nav-icon name="user" class="h-3 w-3" /> Para {{ $task->assignee?->firstName() }}</span>
            @elseif ($task->isAssignedByOther())
                <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-0.5 text-violet-700"><x-nav-icon name="user" class="h-3 w-3" /> De {{ $task->creator?->firstName() }}</span>
            @endif
        </div>
    </div>

    @can('update', $task)
        <button type="button" x-on:click="$dispatch('edit-task', @js($payload))"
            class="rounded-md p-1.5 text-slate-400 opacity-100 transition hover:bg-slate-100 hover:text-slate-700 sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100"
            aria-label="Editar tarea">
            <x-nav-icon name="pencil" class="h-4 w-4" />
        </button>
    @endcan
</li>
