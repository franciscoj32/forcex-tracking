<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Tareas (modulo de Productividad):
 * - Cualquier usuario interno crea tareas para si mismo o para un companero.
 * - Las ven quien la creo y quien la tiene asignada; la Gerencia ve todas.
 * - La marca como hecha quien la tiene asignada o quien la creo.
 * - La edita o la borra quien la creo (y la Gerencia).
 */
class TaskPolicy
{
    public function create(User $user): bool
    {
        return $user->can('use-productivity');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->involved($user, $task) || ($user->isAdmin() && ! $this->isDeveloperPrivate($task, $user));
    }

    public function complete(User $user, Task $task): bool
    {
        return $this->involved($user, $task) || $user->isAdmin();
    }

    public function update(User $user, Task $task): bool
    {
        return (int) $task->creado_por_id === (int) $user->id || ($user->isAdmin() && ! $this->isDeveloperPrivate($task, $user));
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    private function involved(User $user, Task $task): bool
    {
        return in_array((int) $user->id, [(int) $task->creado_por_id, (int) $task->asignado_a_id], true);
    }

    /** Tareas personales de la cuenta oculta de Desarrollo: solo ella las ve. */
    private function isDeveloperPrivate(Task $task, User $user): bool
    {
        return ! $user->isDeveloper() && $task->assignee?->isDeveloper();
    }
}
