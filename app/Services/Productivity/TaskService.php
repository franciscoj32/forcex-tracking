<?php

namespace App\Services\Productivity;

use App\Models\Shipment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Guardado de tareas y aviso al asignado (modulo de Productividad). */
class TaskService
{
    /**
     * Crea o actualiza la tarea. Si se asigna a otra persona (nueva o al
     * cambiar el asignado), le llega un aviso a su campanita.
     */
    public function save(Task $task, array $data, User $user): Task
    {
        $isNew = ! $task->exists;
        $previousAssignee = (int) $task->asignado_a_id;
        $previousDue = $task->vence_en?->toDateTimeString();

        $task->fill($data);

        if ($isNew) {
            $task->creado_por_id = $user->id;
        }

        // Si cambia la hora limite, el aviso "por vencer" se vuelve a enviar.
        if ($task->vence_en?->toDateTimeString() !== $previousDue) {
            $task->recordatorio_enviado_en = null;
        }

        $task->save();

        $assigneeChanged = $isNew || $previousAssignee !== (int) $task->asignado_a_id;

        if ($assigneeChanged && (int) $task->asignado_a_id !== (int) $user->id) {
            $task->loadMissing('assignee')->assignee?->notify(new TaskAssigned($task, $user));
        }

        return $task;
    }

    /** Marca como hecha o la vuelve a pendiente. */
    public function toggle(Task $task, User $user): Task
    {
        $task->forceFill($task->isCompleted()
            ? ['completada_en' => null, 'completada_por_id' => null]
            : ['completada_en' => now(), 'completada_por_id' => $user->id])->save();

        return $task;
    }

    /** "fecha" + "hora" del formulario -> fecha y vence_en. */
    public static function schedule(string $date, ?string $time): array
    {
        $day = Carbon::parse($date)->startOfDay();

        return [
            'fecha' => $day->toDateString(),
            'vence_en' => filled($time) ? $day->copy()->setTimeFromTimeString($time) : null,
        ];
    }

    /**
     * Con quien se puede compartir una tarea: el equipo interno activo.
     * La cuenta de Desarrollo no aparece para los demas.
     *
     * @return Collection<int, User>
     */
    public function teamFor(User $user): Collection
    {
        return User::query()
            ->where('estado', User::ESTADO_ACTIVO)
            ->where(fn ($query) => $query->whereIn('role', User::INTERNAL_ROLES)->orWhere('id', $user->id))
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    /** Busca la RO por su numero de RO u OT. */
    public function findShipment(?string $code): ?Shipment
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        return Shipment::query()
            ->where('routing_order', $code)
            ->orWhere('nro_orden_trabajo', $code)
            ->first(['id', 'routing_order', 'nro_orden_trabajo']);
    }
}
