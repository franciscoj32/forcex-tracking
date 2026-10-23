<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ActionInbox;
use App\Services\Alerts\Alert;
use App\Services\Alerts\AlertBoard;
use App\Services\Alerts\AlertDetector;
use App\Services\Alerts\AlertThresholds;
use App\Services\ManagementMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Inicio: responde "¿que tengo que hacer hoy?".
 *
 * - Bloque "Alertas": plazos detectados por el sistema (App\Services\Alerts).
 * - Todos los internos: bandeja "Requiere tu accion" (App\Services\ActionInbox).
 * - Admin y Comercial: ademas, los numeros del mes (mismo calculo que Reportes).
 */
class DashboardController extends Controller
{
    public function __invoke(ActionInbox $inbox, ManagementMetrics $metrics): View
    {
        $user = auth()->user();

        if ($user->role === User::ROLE_CLIENTE) {
            return $this->clientDashboard($user);
        }

        // Las RO vinculadas se cargan una vez: alimentan las tareas y las alertas.
        $shipments = $inbox->shipmentsFor($user);
        $limits = AlertThresholds::fromSettings();
        [$alerts, $preAlerts] = $this->splitPreAlerts((new AlertBoard(new AlertDetector($limits), $limits))->for($user, $shipments), $user);

        return view('dashboard', [
            // Pizarra de oficina (modulo de Productividad). Apagado: sin consulta.
            'board' => $user->can('use-productivity')
                ? tap(\App\Models\Announcement::query(), fn () => app(\App\Services\Productivity\BoardService::class)->ensureTodayBirthdays())
                    ->active()->boardOrder()->with('author:id,name')->take(3)->get()
                : null,
            'inbox' => $inbox->for($user, $shipments),
            'alerts' => $alerts,
            'preAlerts' => $preAlerts,
            'alertLimits' => $limits,
            'month' => $this->monthPanel($user, $metrics, request('mes')),
        ]);
    }

    /**
     * Las pre-alertas que el usuario puede enviar salen de la lista de alertas y
     * van como aviso destacado arriba del Inicio, aunque se hayan marcado como
     * vistas o pospuestas: siguen visibles hasta que se envien o se retengan.
     *
     * @param  array{activas: Collection, vistas: Collection, pospuestas: Collection}  $alerts
     * @return array{0: array{activas: Collection, vistas: Collection, pospuestas: Collection}, 1: Collection}
     */
    private function splitPreAlerts(array $alerts, User $user): array
    {
        $isSendable = fn (Alert $alert) => $alert->type === Alert::PRE_ARRIVAL && $user->can('sendPreAlert', $alert->shipment);
        $preAlerts = collect();

        foreach ($alerts as $group => $list) {
            [$sendable, $rest] = $list->partition($isSendable);
            $preAlerts = $preAlerts->merge($sendable);
            $alerts[$group] = $rest->values();
        }

        return [$alerts, AlertBoard::sort($preAlerts)];
    }

    /** Numeros del mes para Admin (toda la empresa) y Comercial (sus RO). */
    private function monthPanel(User $user, ManagementMetrics $metrics, ?string $month): ?array
    {
        if (! $user->isAdmin() && ! $user->isComercial()) {
            return null;
        }

        $period = $metrics->period($month);

        // El Admin ve tambien la tendencia de 6 meses: se cargan una sola vez
        // y el mes elegido sale de ese mismo grupo.
        $trendShipments = $user->isAdmin()
            ? $metrics->shipmentsBetween(ManagementMetrics::trendStart($period['end']), $period['end']->copy()->endOfMonth())
            : null;
        $shipments = $trendShipments
            ? $trendShipments->filter(fn ($shipment) => ManagementMetrics::isInPeriod($shipment, $period['start'], $period['end']))->values()
            : $metrics->shipmentsBetween($period['start'], $period['end']);
        $ranking = $metrics->commercialRanking($shipments);
        $scope = $user->isAdmin() ? $shipments : $shipments->where('comercial_id', $user->id);

        return [
            'type' => $user->isAdmin() ? 'admin' : 'comercial',
            'period' => $period,
            'months' => $this->monthOptions(),
            'counts' => $metrics->countsByType($scope),
            'totals' => $metrics->financialTotals($scope),
            'delayed' => $metrics->delayedCount($user->isAdmin() ? null : $user),
            'ranking' => $user->isAdmin() ? $ranking : collect(),
            'position' => $ranking->firstWhere('user_id', $user->id)['position'] ?? null,
            'rankingSize' => $ranking->count(),
            'trend' => $trendShipments ? $metrics->monthlyTrend($period['end'], shipments: $trendShipments) : collect(),
        ];
    }

    /** Ultimos 12 meses para el selector. */
    private function monthOptions(): array
    {
        return collect(range(0, 11))
            ->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset))
            ->mapWithKeys(fn ($month) => [$month->format('Y-m') => ucfirst($month->translatedFormat('F Y'))])
            ->all();
    }

    /** Portal del cliente (hoy sin login; se conserva para cuando se habilite). */
    private function clientDashboard(User $user): View
    {
        $shipments = Shipment::query()
            ->with('client')
            ->where('client_id', $user->client_id)
            ->latest()
            ->get();

        return view('dashboard-client', [
            'activeShipments' => $shipments->where('estado_actual', '!=', 'Finalizado')->values(),
            'recentDocuments' => Document::with('shipment')
                ->whereHas('shipment', fn ($query) => $query->where('client_id', $user->client_id))
                ->latest()
                ->take(6)
                ->get(),
        ]);
    }
}
