<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Models\User;
use App\Services\Alerts\Alert;
use App\Services\Alerts\AlertBoard;
use App\Services\Alerts\AlertDetector;
use App\Services\ShipmentNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Resumen diario por correo de las alertas criticas (se programa en
 * routes/console.php). Usa las mismas reglas que el Inicio:
 * cut-off, ETA vencida o retrasada, demurrage, detention, almacenaje y
 * pre-alerta de llegada.
 *
 * - Va al Customer, al Operador y a quienes siguen la RO con alertas.
 * - Las escaladas (rojas sin atender) tambien van a los Administradores.
 * - Si la RO no tiene a nadie asignado, va a los Administradores.
 * - No incluye las alertas marcadas como vistas o pospuestas.
 */
class SendDailyAlerts extends Command
{
    protected $signature = 'forcex:alertas-diarias {--dry-run : Solo muestra las alertas, no envia correos}';

    protected $description = 'Envia el resumen diario de alertas criticas (cut-off, ETA, demurrage, detention, almacenaje)';

    public function handle(ShipmentNotifier $notifier): int
    {
        $now = CarbonImmutable::now();
        $board = AlertBoard::make();
        $admins = User::where('role', User::ROLE_ADMIN)->where('estado', User::ESTADO_ACTIVO)->pluck('id')->all();

        $shipments = Shipment::query()
            ->whereIn('estado_actual', Shipment::activeStates())
            ->with(['client:id,razon_social', AlertBoard::CONTAINER_COLUMNS, 'userAssignments:id,shipment_id,user_id,alerts_enabled'])
            ->get();

        $alerts = $board->classify(AlertDetector::make()->detect($shipments, $now), $now)
            ->filter(fn (Alert $alert) => $alert->status === Alert::STATUS_ACTIVE);

        /** @var array<int, array<int, string>> $itemsByUser */
        $itemsByUser = [];

        foreach (AlertBoard::sort($alerts) as $alert) {
            $shipment = $alert->shipment;
            $recipients = array_filter([
                $shipment->customer_id,
                $shipment->operador_id,
                ...$shipment->userAssignments->where('alerts_enabled', true)->pluck('user_id')->all(),
            ]);

            if ($recipients === [] || $alert->escalated) {
                $recipients = array_merge($recipients, $admins);
            }

            foreach (array_unique($recipients) as $userId) {
                $itemsByUser[$userId][] = $alert->summary($now);
            }
        }

        $users = User::whereIn('id', array_keys($itemsByUser))->get()->keyBy('id');

        foreach ($itemsByUser as $userId => $items) {
            $user = $users->get($userId);

            if (! $user) {
                continue;
            }

            $this->line("{$user->name} ({$user->email}): ".count($items).' alerta(s)');

            if (! $this->option('dry-run')) {
                $notifier->dailyDigest($user, array_values(array_unique($items)));
            }
        }

        $byType = $alerts->countBy(fn (Alert $alert) => $alert->typeLabel())
            ->map(fn (int $count, string $label) => "{$label}: {$count}")
            ->join(' · ');

        $this->info(($byType !== '' ? $byType : 'Sin alertas').' · Usuarios avisados: '.count($itemsByUser));

        return self::SUCCESS;
    }
}
