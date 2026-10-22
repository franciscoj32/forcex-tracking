<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\ManagementMetrics;
use App\Support\Exports\ShipmentExportRows;
use App\Support\Exports\XlsxExport;
use App\Support\ReportFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportables del modulo de Reportes.
 *
 * - Excel de operaciones: todos los roles internos (sin datos financieros).
 * - Excel financiero: Admin (todas las RO) y Comercial (solo sus RO).
 * - PDF gerencial del mes: Admin.
 * - PDF de estado de una RO: para enviar al cliente (solo datos visibles al cliente).
 */
class ReportExportController extends Controller
{
    public function __construct(private readonly ManagementMetrics $metrics)
    {
    }

    public function operations(Request $request): StreamedResponse
    {
        Gate::authorize('view-reports');

        [$query, $filters] = $this->filteredQuery($request);

        $shipments = $query
            ->with(['client:id,razon_social', 'comercial:id,name', 'customerService:id,name', 'operador:id,name', 'containers:id,shipment_id,numero'])
            ->orderBy('fecha_etd')
            ->get();

        return (new XlsxExport('Consolidado de operaciones'))
            ->sheet('Operaciones', ShipmentExportRows::operationsColumns(), ShipmentExportRows::operationsRows($shipments), $filters)
            ->download('operaciones_'.now()->format('Ymd_His').'.xlsx');
    }

    public function financial(Request $request): StreamedResponse
    {
        Gate::authorize('export-financial');

        [$query, $filters] = $this->filteredQuery($request);
        $user = $request->user();

        // El Comercial solo exporta el estado financiero de sus propias RO.
        if ($user->isComercial()) {
            $query->where('comercial_id', $user->id);
            $filters['Comercial'] = $user->name;
        }

        $shipments = $query
            ->with(['client:id,razon_social', 'comercial:id,name', 'rates', 'provisions'])
            ->orderBy('fecha_etd')
            ->get();

        $export = (new XlsxExport('Estado financiero por RO'))
            ->sheet('Financiero por RO', ShipmentExportRows::financialColumns(), ShipmentExportRows::financialRows($shipments), $filters);

        if ($user->isAdmin()) {
            $export->sheet('Ranking comerciales', ShipmentExportRows::rankingColumns(), $this->metrics->commercialRanking($shipments), $filters);
        }

        return $export->download('financiero_'.now()->format('Ymd_His').'.xlsx');
    }

    /** Resumen gerencial del mes en PDF. */
    public function management(Request $request): Response
    {
        Gate::authorize('view-management');

        $period = $this->metrics->period($request->query('mes'));
        $shipments = $this->metrics->shipmentsBetween($period['start'], $period['end']);

        $pdf = Pdf::loadView('pdf.management', [
            'period' => $period,
            'counts' => $this->metrics->countsByType($shipments),
            'totals' => $this->metrics->financialTotals($shipments),
            'ranking' => $this->metrics->commercialRanking($shipments),
            'trend' => $this->metrics->monthlyTrend($period['end']),
            'delayed' => $this->metrics->delayedShipments(),
            'shipments' => $shipments,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('resumen_gerencial_'.$period['key'].'.pdf');
    }

    /** Reporte de estado de una RO para el cliente (sin costos ni datos internos). */
    public function shipmentStatus(Shipment $shipment): Response
    {
        Gate::authorize('view', $shipment);

        $shipment->load(['client:id,razon_social', 'customerService:id,name,email', 'containers', 'clientVisibleEvents']);

        $pdf = Pdf::loadView('pdf.shipment-status', [
            'shipment' => $shipment,
            'progressIndex' => $shipment->currentTrackingStateIndex(),
        ]);

        return $pdf->download('estado_'.$shipment->routing_order.'.pdf');
    }

    /**
     * Mismos filtros y misma regla de periodo que la pantalla de Reportes.
     * Sin fechas exporta todo el historial.
     *
     * @return array{0: Builder, 1: array<string, string>}
     */
    private function filteredQuery(Request $request): array
    {
        $filters = ReportFilters::fromRequest($request);

        return [
            $this->metrics->periodQuery($filters->from, $filters->to, $filters->metrics()),
            $filters->labels(),
        ];
    }
}
