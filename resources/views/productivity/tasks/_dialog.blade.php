{{--
    Ventana para crear o editar una tarea. Se abre con:
      $dispatch('new-task', { fecha: 'YYYY-MM-DD', ro: '...' })   (nueva)
      $dispatch('edit-task', { ...datos de la tarea })               (editar)
    Si la validacion falla, se vuelve a abrir con lo escrito.
--}}
@php
    $me = auth()->user();
    $blank = [
        'mode' => 'create',
        'action' => route('tareas.store'),
        'task' => null,
        'titulo' => '',
        'descripcion' => '',
        'fecha' => today()->toDateString(),
        'hora' => '',
        'asignado_a_id' => (string) $me->id,
        'ro' => '',
        'delete_url' => null,
        'toggle_url' => null,
        'completada' => false,
        'can_edit' => true,
    ];
    $fromOld = old('_form') === 'tarea';
    $initial = $fromOld
        ? array_merge($blank, [
            'mode' => old('_task') ? 'edit' : 'create',
            'action' => old('_task') ? route('tareas.update', old('_task')) : route('tareas.store'),
            'task' => old('_task'),
            'titulo' => (string) old('titulo'),
            'descripcion' => (string) old('descripcion'),
            'fecha' => (string) old('fecha', today()->toDateString()),
            'hora' => (string) old('hora'),
            'asignado_a_id' => (string) old('asignado_a_id', $me->id),
            'ro' => (string) old('ro'),
        ])
        : array_merge($blank, array_filter([
            'fecha' => request('fecha'),
            'ro' => request('ro'),
        ]));
    $openOnLoad = ($fromOld && $errors->any()) || request()->boolean('nueva');
@endphp

<x-dialog name="tarea" title="Tarea" :show="$openOnLoad" max-width="max-w-lg">
    <div
        x-data="{ form: @js($initial), blank: @js($blank) }"
        x-on:new-task.window="form = Object.assign({}, blank, $event.detail || {}); open = true; $nextTick(() => $refs.titulo.focus())"
        x-on:edit-task.window="form = Object.assign({}, blank, $event.detail, { mode: 'edit' }); open = true; $nextTick(() => $refs.titulo.focus())"
    >
        <form method="POST" :action="form.action" class="space-y-4 p-5">
            @csrf
            <input type="hidden" name="_form" value="tarea">
            <input type="hidden" name="_task" :value="form.task">
            <input type="hidden" name="_method" value="PUT" :disabled="form.mode !== 'edit'">

            <p class="-mt-1 text-xs font-medium uppercase tracking-wide text-slate-400" x-text="form.mode === 'edit' ? 'Editar tarea' : 'Nueva tarea'"></p>

            <div>
                <label class="fx-label" for="task_titulo">¿Qué hay que hacer?</label>
                <input class="fx-input" id="task_titulo" name="titulo" x-ref="titulo" x-model="form.titulo" maxlength="160" required placeholder="Ej. Ir al banco, pedir VB a la naviera">
            </div>

            <div>
                <label class="fx-label" for="task_descripcion">Detalle <span class="font-normal text-slate-400">(opcional)</span></label>
                <textarea class="fx-textarea" id="task_descripcion" name="descripcion" rows="2" maxlength="2000" x-model="form.descripcion"></textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="fx-label" for="task_fecha">Día</label>
                    <input class="fx-input" id="task_fecha" name="fecha" type="date" x-model="form.fecha" required>
                </div>
                <div>
                    <label class="fx-label" for="task_hora">Hora límite <span class="font-normal text-slate-400">(opcional)</span></label>
                    <input class="fx-input" id="task_hora" name="hora" type="time" x-model="form.hora">
                    <p class="mt-1 text-xs text-slate-500" x-text="form.hora ? 'Te avisamos {{ \App\Models\Task::reminderHours() }} h antes.' : 'Sin hora: queda como post-it del día.'"></p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="fx-label" for="task_asignado">Para</label>
                    <select class="fx-select" id="task_asignado" name="asignado_a_id" x-model="form.asignado_a_id">
                        @foreach ($team as $member)
                            <option value="{{ $member->id }}">{{ $member->id === $me->id ? 'Mí ('.$member->name.')' : $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fx-label" for="task_ro">RO u OT <span class="font-normal text-slate-400">(opcional)</span></label>
                    <input class="fx-input" id="task_ro" name="ro" x-model="form.ro" maxlength="60" list="task_ro_options" autocomplete="off" placeholder="Acceso directo a la carga">
                    <datalist id="task_ro_options">
                        @foreach ($roOptions as $code)
                            <option value="{{ $code }}"></option>
                        @endforeach
                    </datalist>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4">
                <div class="flex items-center gap-1">
                    <template x-if="form.mode === 'edit' && form.toggle_url">
                        <button type="submit" form="task-toggle-form" class="fx-btn-secondary h-9" x-text="form.completada ? 'Volver a pendiente' : 'Marcar como hecha'"></button>
                    </template>
                    <template x-if="form.mode === 'edit' && form.delete_url">
                        <button type="submit" form="task-delete-form" class="inline-flex h-9 items-center rounded-md px-3 text-sm font-medium text-rose-700 hover:bg-rose-50">Eliminar</button>
                    </template>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="fx-btn-secondary h-9" x-on:click="open = false">Cancelar</button>
                    <button type="submit" class="fx-btn-primary h-9" x-text="form.mode === 'edit' ? 'Guardar cambios' : 'Crear tarea'"></button>
                </div>
            </div>
        </form>

        <form id="task-toggle-form" method="POST" :action="form.toggle_url" class="hidden">@csrf</form>
        <form id="task-delete-form" method="POST" :action="form.delete_url" class="hidden" x-on:submit="if (! confirm('¿Eliminar esta tarea?')) $event.preventDefault()">
            @csrf
            @method('DELETE')
        </form>
    </div>
</x-dialog>
