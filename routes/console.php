<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| En el hosting (cPanel > Cron Jobs) basta UNA tarea cada minuto:
|
|   * * * * * cd /ruta/de/la/app && php artisan schedule:run >> /dev/null 2>&1
|
| Laravel decide que ejecutar en cada minuto.
*/

// Procesa la cola de correos (eventos A, B, C, notas, alertas). En hosting
// compartido no hay worker permanente: se vacia la cola cada minuto.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

// Resumen diario de alertas criticas: cut-off, ETA, demurrage, detention y almacenaje (hora de Lima).
Schedule::command('forcex:alertas-diarias')
    ->dailyAt(config('forcex.daily_alerts_at', '07:00'))
    ->withoutOverlapping();

// Limpia las acciones viejas sobre alertas (vista / pospuesta) de mas de 90 dias.
Schedule::command('model:prune', ['--model' => [\App\Models\ShipmentAlertState::class]])
    ->dailyAt('03:00')
    ->withoutOverlapping();
