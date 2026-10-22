<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Filtros de Reportes y de sus exportables. Los usan la pagina y los Excel,
 * asi lo que se ve en pantalla es exactamente lo que se descarga.
 */
final class ReportFilters
{
    public function __construct(
        public readonly ?Carbon $from,
        public readonly ?Carbon $to,
        public readonly ?int $clientId,
        public readonly ?string $cargoType,
        public readonly ?int $commercialId,
    ) {
    }

    /**
     * @param  bool  $defaultRange  Sin fechas: ultimos 6 meses (pantalla) o todo el historial (exportables).
     */
    public static function fromRequest(Request $request, bool $defaultRange = false): self
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'tipo_carga' => ['nullable', Rule::in(Shipment::TIPOS_CARGA)],
            'comercial_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $hasDates = filled($data['date_from'] ?? null) || filled($data['date_to'] ?? null);
        $from = filled($data['date_from'] ?? null) ? Carbon::parse($data['date_from'])->startOfDay() : null;
        $to = filled($data['date_to'] ?? null) ? Carbon::parse($data['date_to'])->endOfDay() : null;

        if (! $hasDates && $defaultRange) {
            $from = now()->startOfMonth()->subMonths(5);
            $to = now()->endOfMonth();
        }

        // Solo el Administrador filtra por comercial.
        $commercialId = $request->user()?->isAdmin() && filled($data['comercial_id'] ?? null) ? (int) $data['comercial_id'] : null;

        return new self(
            $from,
            $to,
            filled($data['client_id'] ?? null) ? (int) $data['client_id'] : null,
            $data['tipo_carga'] ?? null,
            $commercialId,
        );
    }

    /** Filtros en el formato de ManagementMetrics. */
    public function metrics(): array
    {
        return array_filter([
            'client_id' => $this->clientId,
            'tipo_carga' => $this->cargoType,
            'comercial_id' => $this->commercialId,
        ]);
    }

    /** Parametros de URL (para los enlaces de exportacion). */
    public function query(): array
    {
        return array_filter([
            'date_from' => $this->from?->toDateString(),
            'date_to' => $this->to?->toDateString(),
            'client_id' => $this->clientId,
            'tipo_carga' => $this->cargoType,
            'comercial_id' => $this->commercialId,
        ]);
    }

    /** Resumen legible para el encabezado de los Excel. */
    public function labels(): array
    {
        return [
            'Desde' => $this->from?->format('d/m/Y') ?? 'Inicio',
            'Hasta' => $this->to?->format('d/m/Y') ?? 'Hoy',
            'Cliente' => $this->clientId ? (string) Client::whereKey($this->clientId)->value('razon_social') : 'Todos',
            'Tipo' => $this->cargoType ?? 'Todos',
            'Comercial' => $this->commercialId ? (string) User::whereKey($this->commercialId)->value('name') : 'Todos',
            'Periodo por' => 'ETD (o fecha de registro si aun no tiene ETD)',
        ];
    }
}
