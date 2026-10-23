<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Task;
use App\Notifications\TaskDueSoon;
use Illuminate\Console\Command;

/**
 * Aviso en la campanita cuando una tarea con hora esta por vencer
 * (FORCEX_TASK_REMINDER_HOURS, por defecto 2 h). Solo una vez por tarea y
 * sin correos. Con el modulo apagado no hace nada.
 */
class SendTaskReminders extends Command
{
    protected $signature = 'forcex:recordatorios-tareas';

    protected $description = 'Avisa en la campanita las tareas que estan por vencer';

    public function handle(): int
    {
        if (! AppSetting::moduleEnabled('productividad')) {
            return self::SUCCESS;
        }

        $now = now();
        $sent = 0;

        Task::query()
            ->pending()
            ->whereNull('recordatorio_enviado_en')
            ->whereBetween('vence_en', [$now->copy()->subHour(), $now->copy()->addHours(Task::reminderHours())])
            ->with('assignee')
            ->chunkById(100, function ($tasks) use (&$sent) {
                foreach ($tasks as $task) {
                    if ($task->assignee?->isActive()) {
                        $task->assignee->notify(new TaskDueSoon($task));
                        $sent++;
                    }

                    $task->forceFill(['recordatorio_enviado_en' => now()])->saveQuietly();
                }
            });

        $this->info("Avisos de tareas por vencer: {$sent}.");

        return self::SUCCESS;
    }
}
