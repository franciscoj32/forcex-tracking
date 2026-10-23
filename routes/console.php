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

// Modulo de Productividad (si esta apagado, los comandos terminan sin consultar nada):
// avisos de tareas por vencer y cumpleanos del dia en la pizarra.
Schedule::command('forcex:recordatorios-tareas')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('forcex:cumpleanos')
    ->dailyAt('00:05')
    ->withoutOverlapping();

// Avisos de la campanita ya leidos con mas de 60 dias.
Schedule::call(function () {
    if (\App\Models\AppSetting::moduleEnabled('productividad')) {
        \Illuminate\Notifications\DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays(60))
            ->delete();
    }
})->name('limpiar-avisos-leidos')->dailyAt('03:10')->withoutOverlapping();

// Limpia las acciones viejas sobre alertas (vista / pospuesta) de mas de 90 dias.
Schedule::command('model:prune', ['--model' => [\App\Models\ShipmentAlertState::class]])
    ->dailyAt('03:00')
    ->withoutOverlapping();
