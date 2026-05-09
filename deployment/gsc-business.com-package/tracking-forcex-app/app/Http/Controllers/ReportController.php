<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Provision;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL]), 403);

        $filters = $request->validate([
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $selectedClientId = filled($filters['client_id'] ?? null) ? (int) $filters['client_id'] : null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        $shipmentBaseQuery = Shipment::query()
            ->when($selectedClientId, fn ($query) => $query->where('client_id', $selectedClientId))
            ->when($dateFrom, fn ($query) => $query->whereDate('fecha_etd', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('fecha_etd', '<=', $dateTo));

        $summary = (clone $shipmentBaseQuery)
            ->selectRaw('COUNT(*) as total_shipments')
            ->selectRaw("SUM(CASE WHEN estado_actual != 'Finalizado' THEN 1 ELSE 0 END) as active_shipments")
            ->selectRaw("SUM(CASE WHEN estado_actual = 'Finalizado' THEN 1 ELSE 0 END) as finished_shipments")
            ->selectRaw('COUNT(DISTINCT client_id) as clients_count')
            ->first();

        $shipmentsByStatus = (clone $shipmentBaseQuery)
            ->selectRaw('estado_actual as label, COUNT(*) as total')
            ->groupBy('estado_actual')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
            ])
            ->values();

        $shipmentsByClient = (clone $shipmentBaseQuery)
            ->leftJoin('clients', 'shipments.client_id', '=', 'clients.id')
            ->selectRaw("COALESCE(clients.razon_social, 'Sin cliente') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
            ])
            ->values();

        $shipmentsByCargoType = (clone $shipmentBaseQuery)
            ->selectRaw('tipo_carga as label, COUNT(*) as total')
            ->groupBy('tipo_carga')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
            ])
            ->values();

        $timelineCounts = (clone $shipmentBaseQuery)
            ->whereNotNull('fecha_etd')
            ->selectRaw("DATE_FORMAT(fecha_etd, '%Y-%m') as month_key, COUNT(*) as total")
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->month_key => (int) $row->total]);

        $currentMonth = now()->startOfMonth();
        $firstTimelineMonth = $timelineCounts->keys()->isNotEmpty()
            ? Carbon::createFromFormat('Y-m', $timelineCounts->keys()->sort()->first())->startOfMonth()
            : null;
        $lastTimelineMonth = $timelineCounts->keys()->isNotEmpty()
            ? Carbon::createFromFormat('Y-m', $timelineCounts->keys()->sort()->last())->startOfMonth()
            : null;
        $rangeEnd = collect([
            $currentMonth,
            filled($dateTo) ? Carbon::parse($dateTo)->startOfMonth() : null,
            $lastTimelineMonth,
        ])->filter()->sort()->last() ?? $currentMonth;
        $rangeStart = collect([
            filled($dateFrom) ? Carbon::parse($dateFrom)->startOfMonth() : null,
            $firstTimelineMonth,
            $rangeEnd->copy()->subMonths(5),
        ])->filter()->sort()->first() ?? $rangeEnd->copy()->subMonths(5);

        if ($rangeStart->diffInMonths($rangeEnd) < 5) {
            $rangeStart = $rangeEnd->copy()->subMonths(5);
        }

        $timelineCursor = $rangeStart->copy();
        $shipmentsTimeline = collect();

        while ($timelineCursor->lte($rangeEnd)) {
            $monthKey = $timelineCursor->format('Y-m');

            $shipmentsTimeline->push([
                'label' => $timelineCursor->translatedFormat('M Y'),
                'short_label' => $timelineCursor->translatedFormat('M'),
                'total' => (int) ($timelineCounts[$monthKey] ?? 0),
                'is_current' => $timelineCursor->equalTo($currentMonth),
                'month_key' => $monthKey,
            ]);

            $timelineCursor->addMonth();
        }

        $provisionsQuery = Provision::query()
            ->join('shipments', 'provisions.shipment_id', '=', 'shipments.id')
            ->when($selectedClientId, fn ($query) => $query->where('shipments.client_id', $selectedClientId))
            ->when($dateFrom, fn ($query) => $query->whereDate('provisions.fecha', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('provisions.fecha', '<=', $dateTo));

        $invoicesQuery = Invoice::query()
            ->join('shipments', 'invoices.shipment_id', '=', 'shipments.id')
            ->when($selectedClientId, fn ($query) => $query->where('shipments.client_id', $selectedClientId))
            ->when($dateFrom, fn ($query) => $query->whereDate('shipments.fecha_etd', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('shipments.fecha_etd', '<=', $dateTo));

        $totalProvisionAmount = (clone $provisionsQuery)->sum('provisions.monto');
        $totalInvoiceAmount = (clone $invoicesQuery)->sum('invoices.monto');
        $pendingProvisions = (clone $provisionsQuery)->where('provisions.estado', 'pendiente')->sum('provisions.monto');
        $pendingInvoices = (clone $invoicesQuery)->whereIn('invoices.estado', ['pendiente', 'emitida'])->sum('invoices.monto');
        $selectedClient = $selectedClientId ? Client::query()->find($selectedClientId, ['id', 'razon_social']) : null;

        return view('reports.index', [
            'summary' => [
                'total_shipments' => (int) ($summary?->total_shipments ?? 0),
                'active_shipments' => (int) ($summary?->active_shipments ?? 0),
                'finished_shipments' => (int) ($summary?->finished_shipments ?? 0),
                'clients_count' => (int) ($summary?->clients_count ?? 0),
            ],
            'shipmentsByStatus' => $shipmentsByStatus,
            'shipmentsByClient' => $shipmentsByClient,
            'shipmentsByCargoType' => $shipmentsByCargoType,
            'shipmentsTimeline' => $shipmentsTimeline,
            'pendingProvisions' => $pendingProvisions,
            'pendingInvoices' => $pendingInvoices,
            'financialTotals' => [
                'provisions' => $totalProvisionAmount,
                'invoices' => $totalInvoiceAmount,
            ],
            'clients' => Client::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'filters' => [
                'client_id' => $selectedClientId ? (string) $selectedClientId : '',
                'client_name' => $selectedClient?->razon_social,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'has_filters' => $selectedClientId !== null || filled($dateFrom) || filled($dateTo),
            ],
        ]);
    }
}
