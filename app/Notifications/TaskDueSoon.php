<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Notification;

/** Aviso in-app: una tarea con hora vence pronto. Lo dispara forcex:recordatorios-tareas. */
class TaskDueSoon extends Notification
{
    public function __construct(public readonly Task $task)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'por_vencer',
            'icono' => 'clock',
            'titulo' => 'Tarea por vencer a las '.$this->task->vence_en->format('H:i'),
            'texto' => $this->task->titulo,
            'url' => route('tareas.index', ['vista' => 'lista', 'tarea' => $this->task->id]),
        ];
    }
}
