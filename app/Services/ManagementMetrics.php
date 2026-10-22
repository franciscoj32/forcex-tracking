<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use App\Support\ShipmentFinancials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Metricas gerenciales compartidas por el dashboard, los reportes y los
 * exportables (Excel/PDF), para que todos muestren los mismos numeros.
 *
 * Periodo de un embarque: su ETD; si aun no tiene ETD, la fecha de creacion
 * de la RO.
 */
class ManagementMetrics
{
    /** @return array{start: Carbon, end: Carbon, key: string, label: string} */
    public function period(?string $month): array
    {
        $start = $month && preg_match('/^\d{4}-\d{2}$/', $month)
            ? Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth()
            : now()->startOfMonth();

        return [
            'start' => $start,
            'end' => $start->copy()->endOfMonth(),
            'key' => $start->format('Y-m'),
            'label' => ucfirst($start->translatedFormat('F Y')),
        ];
    }

    /** Embarques del periodo con lo necesario para calcular el margen. Sin fechas = todo el historial. */
    public function shipmentsBetween(?Carbon $start, ?Carbon $end, array $filters = []): Collection
    {
        return $this->periodQuery($start, $end, $filters)
            ->with(['client:id,razon_social', 'comercial:id,name', 'customerService:id,name', 'operador:id,name', 'rates', 'provisions', 'containers:id,shipment_id,numero'])
            ->orderBy('fecha_etd')
            ->get();
    }

    public function periodQuery(?Carbon $start, ?Carbon $end, array $filters = []): Builder
    {
        return Shipment::query()
            ->when($start || $end, fn (Builder $query) => $query->where(function (Builder $range) use ($start, $end) {
                // Periodo del embarque: su ETD; si no tiene ETD, la fecha de registro.
                $range
                    ->where(fn (Builder $withEtd) => $withEtd
                        ->whereNotNull('fecha_etd')
                        ->when($start, fn (Builder $q) => $q->whereDate('fecha_etd', '>=', $start->toDateString()))
                        ->when($end, fn (Builder $q) => $q->whereDate('fecha_etd', '<=', $end->toDateString())))
                    ->orWhere(fn (Builder $withoutEtd) => $withoutEtd
                        ->whereNull('fecha_etd')
                        ->when($start, fn (Builder $q) => $q->where('created_at', '>=', $start->copy()->startOfDay()))
                        ->when($end, fn (Builder $q) => $q->where('created_at', '<=', $end->copy()->endOfDay())));
            }))
            ->when($filters['client_id'] ?? null, fn (Builder $query, $clientId) => $query->where('client_id', $clientId))
            ->when($filters['comercial_id'] ?? null, fn (Builder $query, $userId) => $query->where('comercial_id', $userId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $userId) => $query->where('customer_id', $userId))
            ->when($filters['operador_id'] ?? null, fn (Builder $query, $userId) => $query->where('operador_id', $userId))
            ->when($filters['tipo_carga'] ?? null, fn (Builder $query, $type) => $query->where('tipo_carga', $type));
    }

    /** @return array{total: int, FCL: int, LCL: int, AEREO: int} */
    public function countsByType(Collection $shipments): array
    {
        $counts = ['total' => $shipments->count()];

        foreach (Shipment::TIPOS_CARGA as $type) {
            $counts[$type] = $shipments->where('tipo_carga', $type)->count();
        }

        return $counts;
    }

    /**
     * Venta, costo y margen en USD. Las RO con montos en PEN sin tipo de
     * cambio no se suman y se cuentan como "incompletas".
     */
    public function financialTotals(Collection $shipments): array
    {
        $sales = 0.0;
        $costs = 0.0;
        $incomplete = 0;

        foreach ($shipments as $shipment) {
            $financials = ShipmentFinancials::for($shipment);

            if ($financials->marginUsd() === null) {
                $incomplete++;
                continue;
            }

            $sales += $financials->salesUsd;
            $costs += $financials->costsUsd;
        }

        $margin = round($sales - $costs, 2);

        return [
            'sales' => round($sales, 2),
            'costs' => round($costs, 2),
            'margin' => $margin,
            'margin_percent' => $sales > 0 ? round(($margin / $sales) * 100, 1) : null,
            'incomplete' => $incomplete,
        ];
    }

    /**
     * Ranking de comerciales por margen comercial en USD (el indicador mas
     * real: 2 FCL grandes pueden valer mas que 50 RO pequenas).
     */
    public function commercialRanking(Collection $shipments): Collection
    {
        $ranking = $shipments
            ->groupBy(fn (Shipment $shipment) => $shipment->comercial_id ?? 0)
            ->map(function (Collection $group, $userId) {
                $totals = $this->financialTotals($group);

                return [
                    'user_id' => $userId ?: null,
                    'name' => $group->first()->comercial?->name ?? 'Venta directa (sin comercial)',
                    'shipments' => $group->count(),
                    'sales' => $totals['sales'],
                    'margin' => $totals['margin'],
                    'margin_percent' => $totals['margin_percent'],
                    'incomplete' => $totals['incomplete'],
                ];
            })
            ->sortByDesc('margin')
            ->values();

        $best = max((float) ($ranking->max('margin') ?? 0), 1);

        return $ranking->map(fn (array $row, int $index) => $row + [
            'position' => $index + 1,
            'bar' => max(0, round(($row['margin'] / $best) * 100)),
        ]);
    }

    /** Primer dia del rango de la tendencia (N meses hasta $until). */
    public static function trendStart(Carbon $until, int $months = 6): Carbon
    {
        return $until->copy()->startOfMonth()->subMonths($months - 1);
    }

    /**
     * Margen de los ultimos N meses (tendencia del dashboard gerencial).
     * $shipments: los embarques del rango ya cargados (con rates y provisions),
     * para no volver a consultarlos.
     */
    public function monthlyTrend(Carbon $until, int $months = 6, array $filters = [], ?Collection $shipments = null): Collection
    {
        $start = self::trendStart($until, $months);
        $shipments ??= $this->shipmentsBetween($start, $until->copy()->endOfMonth(), $filters);

        return collect(range(0, $months - 1))->map(function (int $offset) use ($start, $shipments) {
            $month = $start->copy()->addMonths($offset);
            $inMonth = $shipments->filter(fn (Shipment $shipment) => ($shipment->fecha_etd ?? $shipment->created_at)->format('Y-m') === $month->format('Y-m'));
            $totals = $this->financialTotals($inMonth);

            return [
                'key' => $month->format('Y-m'),
                'label' => ucfirst($month->translatedFormat('M')),
                'shipments' => $inMonth->count(),
                'margin' => $totals['margin'],
            ];
        });
    }

    /**
     * Conteo por mes (ETD, o registro si no tiene ETD) entre dos fechas,
     * incluyendo los meses sin embarques.
     */
    public function shipmentsPerMonth(Collection $shipments, Carbon $from, Carbon $to): Collection
    {
        $cursor = $from->copy()->startOfMonth();
        $months = collect();

        while ($cursor->lte($to) && $months->count() < 24) {
            $key = $cursor->format('Y-m');
            $inMonth = $shipments->filter(fn (Shipment $shipment) => ($shipment->fecha_etd ?? $shipment->created_at)->format('Y-m') === $key);
            $months->push([
                'key' => $key,
                'label' => ucfirst($cursor->translatedFormat('M y')),
                'total' => $inMonth->count(),
                'types' => collect(Shipment::TIPOS_CARGA)->mapWithKeys(fn (string $type) => [$type => $inMonth->where('tipo_carga', $type)->count()])->all(),
            ]);
            $cursor->addMonth();
        }

        return $months;
    }

    /** ETA vencida sin arribo, opcionalmente solo las de un responsable. */
    public function delayedShipments(?User $user = null): Collection
    {
        return $this->delayedQuery($user)
            ->with(['client:id,razon_social', 'customerService:id,name', 'operador:id,name'])
            ->orderBy('fecha_eta')
            ->get();
    }

    /** Cuantas RO estan retrasadas, sin cargarlas (para el numero del Inicio). */
    public function delayedCount(?User $user = null): int
    {
        return $this->delayedQuery($user)->count();
    }

    private function delayedQuery(?User $user): Builder
    {
        return Shipment::query()
            ->delayed()
            ->when($user && $user->isCustomer(), fn (Builder $query) => $query->where('customer_id', $user->id))
            ->when($user && $user->isOperaciones(), fn (Builder $query) => $query->where('operador_id', $user->id))
            ->when($user && $user->isComercial(), fn (Builder $query) => $query->where('comercial_id', $user->id));
    }

    /** El embarque cae en el periodo (misma regla que periodQuery: su ETD o, sin ETD, su registro). */
    public static function isInPeriod(Shipment $shipment, Carbon $start, Carbon $end): bool
    {
        if ($shipment->fecha_etd) {
            $date = $shipment->fecha_etd->toDateString();

            return $date >= $start->toDateString() && $date <= $end->toDateString();
        }

        return $shipment->created_at !== null
            && $shipment->created_at->gte($start->copy()->startOfDay())
            && $shipment->created_at->lte($end->copy()->endOfDay());
    }
}
