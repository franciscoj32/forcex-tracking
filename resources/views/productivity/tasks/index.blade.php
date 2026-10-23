{{-- Tareas: lista (checklist), semana o mes. --}}
@php
    $me = auth()->user();
    $views = [
        'lista' => ['Lista', 'list'],
        'semana' => ['Semana', 'calendar'],
        'mes' => ['Mes', 'calendar-check'],
    ];
    $query = fn (array $changes = []) => array_filter(array_merge(['vista' => $view, 'de' => $filter, 'fecha' => $view === 'lista' ? null : $anchor->toDateString()], $changes));
    $payload = fn (\App\Models\Task $task) => [
        'task' => $task->id,
        'action' => route('tareas.update', $task),
        'titulo' => $task->titulo,
        'descripcion' => (string) $task->descripcion,
        'fecha' => $task->fecha->toDateString(),
        'hora' => $task->vence_en?->format('H:i') ?? '',
        'asignado_a_id' => (string) $task->asignado_a_id,
        'ro' => (string) $task->shipment?->routing_order,
        'delete_url' => $me->can('delete', $task) ? route('tareas.destroy', $task) : null,
        'toggle_url' => $me->can('complete', $task) ? route('tareas.completar', $task) : null,
        'completada' => $task->isCompleted(),
    ];
    $weekdays = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    $today = today();
@endphp

<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block">Equipo</p>
        <h1>Tareas</h1>
    </x-slot>

    <x-slot name="actions">
        @php
            // En el calendario, la tarea nueva cae hoy si hoy esta a la vista; si no, en el dia elegido.
            $newTaskDay = $view === 'lista' || $today->between($start ?? $today, $end ?? $today) ? $today : $anchor;
        @endphp
        <button type="button" x-data x-on:click="$dispatch('new-task', { fecha: @js($newTaskDay->toDateString()) })" class="fx-btn-primary">
            <x-nav-icon name="plus" /> Nueva tarea
        </button>
    </x-slot>

    {{-- Barra: vista, de quien y navegacion del calendario --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-lg bg-slate-100 p-1" role="tablist" aria-label="Formato">
            @foreach ($views as $key => [$label, $icon])
                <a href="{{ route('tareas.index', $query(['vista' => $key, 'fecha' => $key === 'lista' ? null : $anchor->toDateString()])) }}"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition',
                        'bg-white text-slate-900 shadow-sm' => $view === $key,
                        'text-slate-500 hover:text-slate-800' => $view !== $key,
                    ])
                    role="tab" @if ($view === $key) aria-selected="true" @endif>
                    <x-nav-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="inline-flex rounded-lg bg-slate-100 p-1" aria-label="De quién">
            @foreach ($filters as $key => $label)
                <a href="{{ route('tareas.index', $query(['de' => $key])) }}"
                    @class([
                        'rounded-md px-3 py-1.5 text-sm font-medium transition',
                        'bg-white text-slate-900 shadow-sm' => $filter === $key,
                        'text-slate-500 hover:text-slate-800' => $filter !== $key,
                    ])>{{ $label }}</a>
            @endforeach
        </div>

        @if ($view !== 'lista')
            @php
                $prev = $view === 'semana' ? $anchor->copy()->subWeek() : $anchor->copy()->subMonthNoOverflow();
                $next = $view === 'semana' ? $anchor->copy()->addWeek() : $anchor->copy()->addMonthNoOverflow();
                $title = $view === 'semana'
                    ? $start->locale('es')->translatedFormat('j M').' – '.$end->locale('es')->translatedFormat('j M Y')
                    : ucfirst($anchor->locale('es')->translatedFormat('F Y'));
            @endphp
            <div class="flex items-center gap-1 sm:ml-auto">
                <a href="{{ route('tareas.index', $query(['fecha' => $prev->toDateString()])) }}" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900" aria-label="Anterior"><x-nav-icon name="chevron-left" class="h-5 w-5" /></a>
                <a href="{{ route('tareas.index', $query(['fecha' => $today->toDateString()])) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Hoy</a>
                <a href="{{ route('tareas.index', $query(['fecha' => $next->toDateString()])) }}" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900" aria-label="Siguiente"><x-nav-icon name="chevron-right" class="h-5 w-5" /></a>
                <h2 class="ml-2 text-base font-semibold text-slate-900">{{ $title }}</h2>
            </div>
        @endif
    </div>

    {{-- ===================== LISTA ===================== --}}
    @if ($view === 'lista')
        <div class="mt-4 max-w-4xl space-y-4">
            @forelse ($groups as $key => [$label, $tasks])
                <section class="fx-card overflow-hidden">
                    <div @class(['flex items-center justify-between border-b px-4 py-2.5', 'border-rose-100 bg-rose-50/60' => $key === 'atrasadas', 'border-slate-200' => $key !== 'atrasadas'])>
                        <h2 @class(['text-sm font-semibold', 'text-rose-800' => $key === 'atrasadas', 'text-slate-900' => $key !== 'atrasadas'])>{{ $label }}</h2>
                        <span class="text-xs tabular-nums text-slate-500">{{ $tasks->count() }}</span>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($tasks as $task)
                            @include('productivity.tasks._row', ['task' => $task, 'payload' => $payload($task)])
                        @endforeach
                    </ul>
                </section>
            @empty
                <section class="fx-card px-6 py-12 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-accent-50 text-accent-600"><x-nav-icon name="check-circle" class="h-7 w-7" /></span>
                    <p class="mt-4 font-semibold text-slate-900">Todo al día</p>
                    <p class="mt-1 text-slate-500">No hay tareas pendientes en esta vista.</p>
                    <button type="button" x-data x-on:click="$dispatch('new-task')" class="fx-btn-secondary mt-4">Crear una tarea</button>
                </section>
            @endforelse

            @if ($completed->isNotEmpty())
                <details class="fx-card group overflow-hidden">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        <span>Hechas en los últimos 7 días · {{ $completed->count() }}</span>
                        <x-nav-icon name="chevron-right" class="h-4 w-4 transition group-open:rotate-90" />
                    </summary>
                    <ul class="divide-y divide-slate-100 border-t border-slate-200">
                        @foreach ($completed as $task)
                            @include('productivity.tasks._row', ['task' => $task, 'payload' => $payload($task)])
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>

    {{-- ===================== SEMANA ===================== --}}
    @elseif ($view === 'semana')
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-7">
            @for ($day = $start->copy(); $day->lte($end); $day->addDay())
                @php $dayTasks = $byDay->get($day->toDateString(), collect()); $isToday = $day->isSameDay($today); @endphp
                <section x-data @class(['group flex min-h-[9rem] flex-col rounded-xl border bg-white', 'border-brand-300 ring-1 ring-brand-200' => $isToday, 'border-slate-200' => ! $isToday])>
                    <header class="flex items-center justify-between px-3 pb-1 pt-2.5">
                        <p class="text-xs font-medium uppercase tracking-wide {{ $isToday ? 'text-brand-700' : 'text-slate-500' }}">{{ $weekdays[$day->dayOfWeekIso - 1] }}</p>
                        <span @class(['flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold tabular-nums', 'bg-brand-600 text-brand-contrast' => $isToday, 'text-slate-900' => ! $isToday])>{{ $day->day }}</span>
                    </header>
                    <div class="flex-1 space-y-1 px-2 pb-2">
                        @foreach ($dayTasks as $task)
                            @include('productivity.tasks._chip', ['task' => $task, 'payload' => $payload($task), 'compact' => false])
                        @endforeach
                    </div>
                    <button type="button" x-on:click="$dispatch('new-task', { fecha: @js($day->toDateString()) })"
                        class="mx-2 mb-2 flex items-center justify-center gap-1 rounded-md py-1 text-xs font-medium text-slate-400 transition hover:bg-slate-50 hover:text-brand-700 lg:opacity-0 lg:group-hover:opacity-100">
                        <x-nav-icon name="plus" class="h-3.5 w-3.5" /> Agregar
                    </button>
                </section>
            @endfor
        </div>

    {{-- ===================== MES ===================== --}}
    @else
        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-[11px] font-medium uppercase tracking-wide text-slate-500">
                @foreach ($weekdays as $weekday)
                    <div class="py-2">{{ $weekday }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7">
                @for ($day = $start->copy(); $day->lte($end); $day->addDay())
                    @php
                        $dayTasks = $byDay->get($day->toDateString(), collect());
                        $isToday = $day->isSameDay($today);
                        $inMonth = $day->isSameMonth($anchor);
                    @endphp
                    <div x-data x-on:click="$dispatch('new-task', { fecha: @js($day->toDateString()) })"
                        @class([
                            'group min-h-[4.5rem] cursor-pointer border-b border-r border-slate-100 p-1 transition hover:bg-brand-50/40 sm:min-h-[7rem] sm:p-1.5',
                            'bg-slate-50/70' => ! $inMonth,
                        ])
                        title="Agregar tarea el {{ $day->format('d/m') }}">
                        <div class="flex items-center justify-between">
                            <span class="hidden text-slate-300 transition group-hover:text-brand-600 sm:inline"><x-nav-icon name="plus" class="h-3.5 w-3.5" /></span>
                            <span @class([
                                'ml-auto flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                                'bg-brand-600 text-brand-contrast' => $isToday,
                                'text-slate-900' => ! $isToday && $inMonth,
                                'text-slate-400' => ! $isToday && ! $inMonth,
                            ])>{{ $day->day }}</span>
                        </div>
                        {{-- Escritorio: hasta 3 tareas; celular: puntos --}}
                        <div class="mt-1 hidden space-y-0.5 sm:block">
                            @foreach ($dayTasks->take(3) as $task)
                                @include('productivity.tasks._chip', ['task' => $task, 'payload' => $payload($task), 'compact' => true])
                            @endforeach
                            @if ($dayTasks->count() > 3)
                                <a href="{{ route('tareas.index', $query(['vista' => 'semana', 'fecha' => $day->toDateString()])) }}" x-on:click.stop class="block px-1.5 text-[11px] font-medium text-slate-500 hover:text-brand-700">+{{ $dayTasks->count() - 3 }} más</a>
                            @endif
                        </div>
                        @if ($dayTasks->isNotEmpty())
                            <a href="{{ route('tareas.index', $query(['vista' => 'semana', 'fecha' => $day->toDateString()])) }}" x-on:click.stop class="mt-1 flex flex-wrap gap-0.5 sm:hidden" aria-label="{{ $dayTasks->count() }} tareas">
                                @foreach ($dayTasks->take(4) as $task)
                                    <span @class(['h-1.5 w-1.5 rounded-full', 'bg-slate-300' => $task->isCompleted(), 'bg-rose-500' => ! $task->isCompleted() && $task->isOverdue($now), 'bg-amber-400' => ! $task->isCompleted() && ! $task->isOverdue($now) && $task->isPostIt(), 'bg-brand-500' => ! $task->isCompleted() && ! $task->isOverdue($now) && ! $task->isPostIt()])></span>
                                @endforeach
                            </a>
                        @endif
                    </div>
                @endfor
            </div>
        </div>
        <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-brand-500"></span>Con hora</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-400"></span>Post-it del día</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rose-500"></span>Atrasada</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-slate-300"></span>Hecha</span>
            <span>Haz clic en un día para agregar una tarea.</span>
        </p>
    @endif

    @include('productivity.tasks._dialog')
</x-app-layout>
