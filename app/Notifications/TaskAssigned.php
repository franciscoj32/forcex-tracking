<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Notification;

/** Aviso in-app: un companero te asigno una tarea. Sin correo. */
class TaskAssigned extends Notification
{
    public function __construct(public readonly Task $task, public readonly User $by)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'tarea',
            'icono' => 'tasks',
            'titulo' => $this->by->firstName().' te asignó una tarea',
            'texto' => $this->task->titulo.' · '.$this->task->whenLabel(),
            'url' => route('tareas.index', ['vista' => 'lista', 'tarea' => $this->task->id]),
        ];
    }
}
