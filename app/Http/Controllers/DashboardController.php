<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $today = now()->startOfDay();

        if ($user->role === User::ROLE_CLIENTE) {
            $shipments = Shipment::query()
                ->with('client')
                ->where('client_id', $user->client_id)
                ->latest()
                ->get();

            return view('dashboard', [
                'mode' => 'cliente',
                'activeShipments' => $shipments->where('estado_actual', '!=', 'Finalizado')->take(6),
                'latestStatus' => $shipments->take(5),
                'upcomingArrivals' => $shipments
                    ->filter(fn (Shipment $shipment) => $shipment->fecha_eta && $shipment->fecha_eta->gte($today))
                    ->sortBy('fecha_eta')
                    ->take(5),
                'recentDocuments' => Document::with('shipment')
                    ->whereHas('shipment', fn ($query) => $query->where('client_id', $user->client_id))
                    ->latest()
                    ->take(6)
                    ->get(),
            ]);
        }

        $summary = Shipment::query()
            ->selectRaw('COUNT(*) as total_shipments')
            ->selectRaw("SUM(CASE WHEN estado_actual = 'En transito' THEN 1 ELSE 0 END) as in_transit")
            ->selectRaw("SUM(CASE WHEN estado_actual IN ('Arribo a puerto', 'Descarga', 'Ingreso a almacen') THEN 1 ELSE 0 END) as arrived")
            ->selectRaw("SUM(CASE WHEN estado_actual = 'Finalizado' THEN 1 ELSE 0 END) as finished")
            ->selectRaw('SUM(CASE WHEN fecha_eta BETWEEN ? AND ? THEN 1 ELSE 0 END) as upcoming_eta', [
                $today->toDateString(),
                $today->copy()->addDays(7)->toDateString(),
            ])
            ->first();

        $assignedShipmentsFeed = $user->assignedShipments()
            ->with([
                'client',
                'events' => fn ($query) => $query->latest('fecha')->take(1),
            ])
            ->wherePivot('alerts_enabled', true)
            ->orderByRaw('CASE WHEN shipments.fecha_eta IS NULL THEN 1 ELSE 0 END')
            ->orderBy('shipments.fecha_eta')
            ->orderByDesc('shipments.updated_at')
            ->get()
            ->unique('id')
            ->values();

        $recentEventThreshold = $today->copy()->subDays(4);
        $arrivalWindowEnd = $today->copy()->addDays(3)->endOfDay();

        $assignedActiveShipments = $assignedShipmentsFeed
            ->filter(fn (Shipment $shipment) => $shipment->estado_actual !== 'Finalizado')
            ->take(6)
            ->values();

        $statusAlerts = $assignedShipmentsFeed
            ->filter(function (Shipment $shipment) use ($recentEventThreshold) {
                $latestEvent = $shipment->events->first();

                return $latestEvent?->fecha && $latestEvent->fecha->gte($recentEventThreshold);
            })
            ->sortByDesc(fn (Shipment $shipment) => $shipment->events->first()?->fecha?->getTimestamp() ?? 0)
            ->take(5)
            ->values();

        $arrivalAlerts = $assignedShipmentsFeed
            ->filter(function (Shipment $shipment) use ($today, $arrivalWindowEnd, $recentEventThreshold) {
                $hasUpcomingEta = $shipment->fecha_eta
                    && $shipment->fecha_eta->between($today, $arrivalWindowEnd)
                    && ! in_array($shipment->estado_actual, ['Entregado', 'Finalizado'], true);

                $latestEvent = $shipment->events->first();
                $hasRecentArrival = in_array($shipment->estado_actual, ['Arribo a puerto', 'Descarga', 'Ingreso a almacen'], true)
                    && $latestEvent?->fecha
                    && $latestEvent->fecha->gte($recentEventThreshold);

                return $hasUpcomingEta || $hasRecentArrival;
            })
            ->sortBy(fn (Shipment $shipment) => $shipment->fecha_eta?->getTimestamp() ?? PHP_INT_MAX)
            ->take(5)
            ->values();

        return view('dashboard', [
            'mode' => 'interno',
            'totalShipments' => (int) ($summary?->total_shipments ?? 0),
            'inTransit' => (int) ($summary?->in_transit ?? 0),
            'arrived' => (int) ($summary?->arrived ?? 0),
            'finished' => (int) ($summary?->finished ?? 0),
            'upcomingEta' => (int) ($summary?->upcoming_eta ?? 0),
            'assignedActiveShipments' => $assignedActiveShipments,
            'statusAlerts' => $statusAlerts,
            'arrivalAlerts' => $arrivalAlerts,
            'recentMovements' => Shipment::with(['client', 'events' => fn ($query) => $query->latest('fecha')->take(1)])
                ->latest('updated_at')
                ->take(6)
                ->get(),
        ]);
    }
}
