<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ManagementMetrics;
use App\Support\ChartPalette;
use App\Support\ReportFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Reportes: el lugar para analizar un rango de fechas y exportar.
 * Usa el mismo calculo que el Inicio (ManagementMetrics) y los mismos filtros
 * que los Excel (ReportFilters).
 */
class ReportController extends Controller
{
    public function index(Request $request, ManagementMetrics $metrics): View
    {
        Gate::authorize('view-reports');

        $filters = ReportFilters::fromRequest($request, defaultRange: true);
        $user = $request->user();
        $canSeeMoney = $user->can('view-management');

        $shipments = $metrics->shipmentsBetween($filters->from, $filters->to, $filters->metrics());
        $statesBeforeArrival = Shipment::statesBeforeArrival();
        $today = now()->startOfDay();

        $byStatus = collect(Shipment::TRACKING_STATES)
            ->map(fn (string $state) => [
                'label' => $state,
                'total' => $shipments->where('estado_actual', $state)->count(),
                'stage' => ChartPalette::stage($state),
            ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();

        // Totales por etapa para la barra apilada de arriba.
        $byStage = collect(ChartPalette::STAGES)
            ->map(fn (array $stage, string $key) => $stage + [
                'total' => $shipments->filter(fn (Shipment $shipment) => ChartPalette::stageOf($shipment->estado_actual) === $key)->count(),
            ]);

        $topClients = $shipments
            ->groupBy('client_id')
            ->map(fn ($group) => [
                'label' => $group->first()->client?->razon_social ?? 'Sin cliente',
                'total' => $group->count(),
                'margin' => $canSeeMoney ? $metrics->financialTotals($group)['margin'] : null,
            ])
            ->sortByDesc('total')
            ->take(8)
            ->values();

        return view('reports.index', [
            'filters' => $filters,
            'counts' => $metrics->countsByType($shipments),
            'active' => $shipments->where('estado_actual', '!=', 'Finalizado')->count(),
            'delayed' => $shipments->filter(fn (Shipment $shipment) => $shipment->fecha_eta
                && $shipment->fecha_eta->lt($today)
                && in_array($shipment->estado_actual, $statesBeforeArrival, true))->count(),
            'totals' => $canSeeMoney ? $metrics->financialTotals($shipments) : null,
            'receivable' => $canSeeMoney ? $this->receivableUsd($shipments->pluck('id')->all()) : null,
            'perMonth' => $metrics->shipmentsPerMonth($shipments, $filters->from ?? ($shipments->min('fecha_etd') ?? now()->subMonths(5)), $filters->to ?? now()),
            'byStatus' => $byStatus,
            'byStage' => $byStage,
            'topClients' => $topClients,
            'ranking' => $canSeeMoney ? $metrics->commercialRanking($shipments) : collect(),
            'canSeeMoney' => $canSeeMoney,
            'clients' => Client::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'commercials' => $user->isAdmin()
                ? User::query()->where('role', User::ROLE_COMERCIAL)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    /** Facturas al cliente por cobrar (pendientes o emitidas), en USD con el tipo de cambio de cada RO. */
    private function receivableUsd(array $shipmentIds): float
    {
        if ($shipmentIds === []) {
            return 0.0;
        }

        return round((float) Invoice::query()
            ->join('shipments', 'invoices.shipment_id', '=', 'shipments.id')
            ->whereIn('invoices.shipment_id', $shipmentIds)
            ->whereIn('invoices.estado', ['pendiente', 'emitida'])
            ->selectRaw("COALESCE(SUM(CASE WHEN invoices.moneda = 'USD' THEN invoices.monto ELSE invoices.monto / NULLIF(shipments.tipo_cambio, 0) END), 0) as total")
            ->value('total'), 2);
    }
}
