<?php

namespace App\Http\Controllers\Productivity;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Task;
use App\Services\Productivity\TaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Tareas: lista (checklist), semana y mes. Tambien atiende el post-it flotante
 * (alta rapida y marcar como hecha por JSON).
 */
class TaskController extends Controller
{
    public const VIEWS = ['lista', 'semana', 'mes'];

    public function __construct(private readonly TaskService $tasks)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('create', Task::class);

        $user = $request->user();
        $view = in_array($request->query('vista'), self::VIEWS, true)
            ? $request->query('vista')
            : $request->session()->get('tareas.vista', 'lista');
        $request->session()->put('tareas.vista', $view);

        $filters = ['mias' => 'Mías', 'asignadas' => 'Asignadas por mí'] + ($user->isAdmin() ? ['equipo' => 'Todo el equipo'] : []);
        $filter = array_key_exists((string) $request->query('de'), $filters) ? (string) $request->query('de') : 'mias';

        $anchor = rescue(fn () => Carbon::parse((string) $request->query('fecha', today()->toDateString()))->startOfDay(), today(), false);
        $now = now();

        $base = fn (): Builder => Task::query()
            ->visibleTo($user)
            ->when($filter === 'mias', fn ($query) => $query->where('asignado_a_id', $user->id))
            ->when($filter === 'asignadas', fn ($query) => $query->where('creado_por_id', $user->id)->where('asignado_a_id', '!=', $user->id))
            ->with(['shipment:id,routing_order', 'assignee:id,name', 'creator:id,name']);

        $data = [
            'view' => $view,
            'filter' => $filter,
            'filters' => $filters,
            'anchor' => $anchor,
            'now' => $now,
            'team' => $this->tasks->teamFor($user),
            'highlight' => $request->integer('tarea') ?: null,
            // Sugerencias para el campo RO: las cargas activas mas recientes.
            'roOptions' => Shipment::query()->whereIn('estado_actual', Shipment::activeStates())
                ->latest('id')->limit(300)->pluck('routing_order'),
        ];

        if ($view === 'lista') {
            $pending = $base()->pending()->agenda()->get();
            $today = today();
            $data['groups'] = collect([
                'atrasadas' => ['Atrasadas', $pending->filter(fn (Task $task) => $task->isOverdue($now))],
                'hoy' => ['Hoy', $pending->filter(fn (Task $task) => ! $task->isOverdue($now) && $task->fecha->isSameDay($today))],
                'manana' => ['Mañana', $pending->filter(fn (Task $task) => $task->fecha->isSameDay($today->copy()->addDay()))],
                'semana' => ['Próximos 7 días', $pending->filter(fn (Task $task) => $task->fecha->gt($today->copy()->addDay()) && $task->fecha->lte($today->copy()->addDays(7)))],
                'despues' => ['Más adelante', $pending->filter(fn (Task $task) => $task->fecha->gt($today->copy()->addDays(7)))],
            ])->filter(fn (array $group) => $group[1]->isNotEmpty());
            $data['pendingCount'] = $pending->count();
            $data['completed'] = $base()->completed()->where('completada_en', '>=', $now->copy()->subDays(7))->latest('completada_en')->take(20)->get();
        } else {
            [$start, $end] = $view === 'semana'
                ? [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()]
                : [$anchor->copy()->startOfMonth()->startOfWeek(), $anchor->copy()->endOfMonth()->endOfWeek()];

            $data['start'] = $start;
            $data['end'] = $end;
            $data['byDay'] = $base()->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
                ->agenda()->get()
                ->groupBy(fn (Task $task) => $task->fecha->toDateString());
        }

        return view('productivity.tasks.index', $data);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', Task::class);

        $task = $this->tasks->save(new Task(), $this->validated($request), $request->user());

        if ($request->expectsJson()) {
            return response()->json(['task' => $task->load('shipment:id,routing_order', 'creator:id,name')->toPostItArray()], 201);
        }

        return back()->with('status', (int) $task->asignado_a_id === (int) $request->user()->id
            ? 'Tarea creada.'
            : 'Tarea asignada a '.$task->assignee->name.'. Le llegará un aviso.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $this->tasks->save($task, $this->validated($request, $task), $request->user());

        return back()->with('status', 'Tarea actualizada.');
    }

    /** Checkbox: hecha / pendiente. */
    public function complete(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        Gate::authorize('complete', $task);

        $this->tasks->toggle($task, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['completada' => $task->isCompleted()]);
        }

        return back();
    }

    public function destroy(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', 'Tarea eliminada.');
    }

    private function validated(Request $request, ?Task $task = null): array
    {
        $user = $request->user();
        $teamIds = $this->tasks->teamFor($user)->pluck('id')->all();

        // Al editar se conserva el asignado aunque ya no este en la lista (inactivo).
        if ($task) {
            $teamIds[] = (int) $task->asignado_a_id;
        }

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'fecha' => ['nullable', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
            'asignado_a_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'ro' => ['nullable', 'string', 'max:60'],
            'shipment_id' => ['nullable', 'integer', 'exists:shipments,id'],
        ], [
            'titulo.required' => 'Escribe qué hay que hacer.',
            'asignado_a_id.in' => 'Elige a alguien del equipo.',
        ], ['hora' => 'hora', 'ro' => 'RO']);

        $shipmentId = $data['shipment_id'] ?? null;

        if (! $shipmentId && filled($data['ro'] ?? null)) {
            $shipment = $this->tasks->findShipment($data['ro']);

            if (! $shipment) {
                throw ValidationException::withMessages(['ro' => 'No encontramos la RO u OT "'.$data['ro'].'".'])->errorBag('default');
            }

            $shipmentId = $shipment->id;
        }

        return TaskService::schedule($data['fecha'] ?? today()->toDateString(), $data['hora'] ?? null) + [
            'titulo' => trim($data['titulo']),
            'descripcion' => $data['descripcion'] ?? null,
            'asignado_a_id' => $data['asignado_a_id'] ?? ($task?->asignado_a_id ?? $user->id),
            'shipment_id' => $shipmentId,
        ];
    }
}
