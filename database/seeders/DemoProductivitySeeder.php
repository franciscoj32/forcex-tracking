<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AppSetting;
use App\Models\Shipment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\Productivity\BoardService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Datos de ejemplo del modulo de Productividad y Cultura:
 * enciende el modulo, crea tareas (post-its, con hora, atrasadas, vinculadas
 * a RO, asignadas entre companeros), anuncios en la pizarra, cumpleanos
 * (uno hoy) y los avisos de la campanita.
 *
 * Uso: php artisan db:seed --class=DemoProductivitySeeder
 * Si ya hay tareas, no vuelve a crearlas (no duplica).
 */
class DemoProductivitySeeder extends Seeder
{
    public function run(BoardService $board): void
    {
        AppSetting::current()->forceFill(['modulo_productividad' => true])->save();
        AppSetting::flushCache();

        $users = User::query()->get()->keyBy('username');
        $dev = $users->first(fn (User $user) => $user->isDeveloper());
        $manager = $users->get('dilcia-estela') ?? $users->first(fn (User $user) => $user->role === User::ROLE_ADMIN) ?? $dev;
        $juan = $users->get('juan_comercial');
        $ana = $users->get('ana_comercial');
        $maria = $users->get('maria_customer');
        $diego = $users->get('diego_customer');
        $carla = $users->get('carla_operaciones');
        $luis = $users->get('luis_operaciones');

        if (! $manager) {
            $this->command?->warn('No hay un Administrador / Gerente: no se crean datos de ejemplo.');

            return;
        }

        $this->birthdays(compact('juan', 'maria', 'diego', 'carla', 'manager'));

        if (Task::query()->exists()) {
            $this->command?->info('Ya hay tareas: no se crean de nuevo. Modulo encendido.');

            return;
        }

        $ros = Shipment::query()->whereIn('estado_actual', Shipment::activeStates())->orderBy('id')->take(6)->get(['id', 'routing_order']);
        $ro = fn (int $index) => $ros->get($index % max(1, $ros->count()))?->id;
        $today = today();
        $at = fn (int $days, string $time) => $today->copy()->addDays($days)->setTimeFromTimeString($time);

        // Tareas de quien entra con "admin" (Desarrollo) o, si no existe, de la Gerente.
        $me = $dev ?? $manager;

        $this->task($me, $me, 'Revisar cotizaciones pendientes de la semana', $today);
        $this->task($me, $me, 'Llamar al banco por la carta fianza', $today);
        $this->task($me, $me, 'Enviar reporte de margen a Gerencia', $today->copy()->subDay());
        $this->task($me, $me, 'Reunión con la naviera COSCO', $today, now()->addMinutes(75)->startOfMinute(), 'Revisar tarifas Asia y espacio para noviembre.');
        $this->task($me, $me, 'Revisar tarifas del Q4', $today->copy()->addDay(), $at(1, '10:00'));
        $this->task($me, $me, 'Seguimiento de documentos de la carga', $today->copy()->addDays(5), $at(5, '15:00'), null, $ro(0));
        $this->task($me, $me, 'Preparar presentación para cliente nuevo', $today->copy()->addDays(9), $at(9, '11:30'));
        $this->task($me, $me, 'Renovar dominio y correos', $today->copy()->addDays(16));
        $this->task($me, $me, 'Actualizar contactos de proveedores', $today->copy()->subDay(), null, null, null, completed: true);

        // Asignada por la Gerente: llega aviso a la campanita.
        if ($me->id !== $manager->id) {
            $this->task($manager, $me, 'Aprobar los clientes nuevos pendientes', $today, $at(0, '17:00'), 'Están en Clientes → Por aprobar.', notify: true);
            $this->task($manager, $me, 'Revisar la configuración de alertas', $today->copy()->addDays(2), $at(2, '09:30'), null, null, notify: true);
        }

        // Tareas del equipo (se ven con "Todo el equipo").
        if ($juan) {
            $this->task($manager, $juan, 'Enviar cotización a Andean Retail', $today->copy()->addDay(), $at(1, '11:00'), null, $ro(1), notify: true);
            $this->task($juan, $juan, 'Visitar a Minera Pacífico', $today);
        }
        if ($maria) {
            $this->task($maria, $maria, 'Confirmar la pre-alerta con la agencia de aduana', $today, $at(0, '16:00'), null, $ro(2));
            $this->task($maria, $maria, 'Llamar al cliente por el cambio de ETA', $today->copy()->subDays(2), $at(-2, '12:00'), null, $ro(3));
        }
        if ($maria && $carla) {
            $this->task($maria, $carla, 'Registrar la tarja apenas llegue la carga', $today->copy()->addDays(2), $at(2, '10:00'), null, $ro(4), notify: true);
        }
        if ($luis) {
            $this->task($luis, $luis, 'Pedir el VB a la naviera', $today, null, null, $ro(5));
            $this->task($luis, $luis, 'Coordinar el retiro con el transportista', $today->copy()->addDays(3), $at(3, '08:30'), null, $ro(0));
        }
        if ($ana && $diego) {
            $this->task($ana, $diego, 'Enviar el tracking semanal al cliente', $today->copy()->addDays(4), $at(4, '09:00'), null, $ro(1), notify: true);
        }

        // Pizarra: anuncios de la Gerente (avisan a todo el equipo).
        $board->publish([
            'titulo' => 'Reunión general hoy a las 4:00 PM',
            'cuerpo' => "Sala de reuniones del 2.° piso.\nRevisaremos los embarques de noviembre y las metas del mes.",
            'fijado' => true,
            'expira_en' => $today->copy()->addDay()->endOfDay(),
        ], $manager);
        $board->publish([
            'titulo' => 'Feriado: el viernes la oficina estará cerrada',
            'cuerpo' => 'Las cargas en tránsito siguen con su Customer de turno. Cualquier urgencia, al celular de guardia.',
            'fijado' => false,
            'expira_en' => $today->copy()->addDays(7)->endOfDay(),
        ], $manager);
        $board->publish([
            'titulo' => 'Nuevo: las pre-alertas ahora se envían desde la ventana del Inicio',
            'cuerpo' => 'Cuando una RO está por llegar, aparece el aviso arriba del Inicio. Ahí eliges a quién enviarla.',
            'fijado' => false,
            'expira_en' => $today->copy()->addDays(10)->endOfDay(),
        ], $manager);

        // Cumpleanos de hoy en la pizarra y aviso "tarea por vencer".
        $board->postBirthdays();
        Artisan::call('forcex:recordatorios-tareas');

        $this->command?->info(sprintf(
            'Modulo encendido. Tareas: %d · Anuncios: %d · Avisos: %d.',
            Task::count(),
            Announcement::count(),
            \Illuminate\Notifications\DatabaseNotification::count(),
        ));
    }

    /** Cumpleanos de ejemplo: Juan hoy (para ver el saludo) y otros este mes. */
    private function birthdays(array $people): void
    {
        $today = today();
        $dates = [
            'juan' => $today->copy()->subYears(32),
            'maria' => $today->copy()->day(min(25, $today->daysInMonth))->subYears(28),
            'diego' => $today->copy()->day(min(3, $today->daysInMonth))->subYears(35),
            'carla' => $today->copy()->addMonths(2)->subYears(30),
            'manager' => $today->copy()->day(min(18, $today->daysInMonth))->subYears(41),
        ];

        foreach ($dates as $key => $date) {
            $user = $people[$key] ?? null;

            if ($user && ! $user->fecha_nacimiento) {
                $user->forceFill(['fecha_nacimiento' => $date->toDateString()])->save();
            }
        }
    }

    private function task(User $creator, User $assignee, string $title, Carbon $day, ?Carbon $due = null, ?string $detail = null, ?int $shipmentId = null, bool $notify = false, bool $completed = false): Task
    {
        $task = new Task([
            'titulo' => $title,
            'descripcion' => $detail,
            'fecha' => ($due ?? $day)->toDateString(),
            'vence_en' => $due,
            'shipment_id' => $shipmentId,
            'asignado_a_id' => $assignee->id,
        ]);
        $task->creado_por_id = $creator->id;

        if ($completed) {
            $task->completada_en = now()->subHours(3);
            $task->completada_por_id = $assignee->id;
        }

        $task->save();

        if ($notify && $creator->id !== $assignee->id) {
            $assignee->notify(new TaskAssigned($task, $creator));
        }

        return $task;
    }
}
