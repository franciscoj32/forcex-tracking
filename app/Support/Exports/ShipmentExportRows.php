<?php

namespace App\Support\Exports;

use App\Models\Shipment;
use App\Support\ShipmentFinancials;
use Illuminate\Support\Collection;

/**
 * Columnas y filas de los exportables de embarques. Se comparten entre el
 * listado de Embarques y el modulo de Reportes para que coincidan.
 */
final class ShipmentExportRows
{
    /** Consolidado de operaciones (sin datos financieros). */
    public static function operationsColumns(): array
    {
        return [
            'ro' => ['label' => 'RO', 'width' => 16],
            'ot' => ['label' => 'OT', 'width' => 16],
            'tracking' => ['label' => 'Codigo tracking', 'width' => 15],
            'cliente' => ['label' => 'Cliente', 'width' => 32],
            'tipo' => ['label' => 'Tipo', 'width' => 9],
            'incoterm' => ['label' => 'Incoterm', 'width' => 10],
            'origen' => ['label' => 'Origen', 'width' => 22],
            'destino' => ['label' => 'Destino', 'width' => 22],
            'naviera' => ['label' => 'Naviera / aerolinea', 'width' => 18],
            'nave' => ['label' => 'Nave / vuelo', 'width' => 18],
            'viaje' => ['label' => 'Viaje', 'width' => 10],
            'bl' => ['label' => 'BL / AWB', 'width' => 16],
            'contenedores' => ['label' => 'Contenedores', 'width' => 26],
            'peso_kg' => ['label' => 'Peso (kg)', 'format' => 'number', 'total' => true],
            'volumen' => ['label' => 'Volumen (CBM)', 'format' => 'number', 'total' => true],
            'cut_off' => ['label' => 'Cut-off', 'format' => 'date'],
            'etd' => ['label' => 'ETD', 'format' => 'date'],
            'eta' => ['label' => 'ETA interna', 'format' => 'date'],
            'eta_cliente' => ['label' => 'ETA publicada', 'format' => 'date'],
            'dias_retraso' => ['label' => 'Dias de retraso', 'format' => 'integer'],
            'estado' => ['label' => 'Estado', 'width' => 20],
            'ubicacion' => ['label' => 'Donde esta ahora', 'width' => 30],
            'tarja' => ['label' => 'Tarja', 'format' => 'date'],
            'almacen' => ['label' => 'Almacen', 'width' => 18],
            'endoso' => ['label' => 'Endoso', 'width' => 12],
            'comercial' => ['label' => 'Comercial', 'width' => 18],
            'customer' => ['label' => 'Customer', 'width' => 18],
            'operador' => ['label' => 'Operador', 'width' => 18],
        ];
    }

    public static function operationsRows(Collection $shipments): Collection
    {
        return $shipments->map(fn (Shipment $shipment) => [
            'ro' => $shipment->routing_order,
            'ot' => $shipment->nro_orden_trabajo,
            'tracking' => $shipment->tracking_code,
            'cliente' => $shipment->client?->razon_social,
            'tipo' => $shipment->tipo_carga,
            'incoterm' => $shipment->incoterm,
            'origen' => trim($shipment->puerto_carga.' ('.$shipment->origen.')'),
            'destino' => trim($shipment->puerto_descarga.' ('.$shipment->destino_final.')'),
            'naviera' => $shipment->naviera,
            'nave' => $shipment->nave,
            'viaje' => $shipment->viaje,
            'bl' => $shipment->bl,
            'contenedores' => $shipment->containers->pluck('numero')->join(', '),
            'peso_kg' => $shipment->peso !== null ? (float) $shipment->peso : null,
            'volumen' => $shipment->volumen !== null ? (float) $shipment->volumen : null,
            'cut_off' => $shipment->fecha_cut_off,
            'etd' => $shipment->fecha_etd,
            'eta' => $shipment->fecha_eta,
            'eta_cliente' => $shipment->fecha_eta_cliente,
            'dias_retraso' => in_array($shipment->estado_actual, Shipment::statesBeforeArrival(), true) ? $shipment->daysLate() : 0,
            'estado' => $shipment->estado_actual,
            'ubicacion' => $shipment->ubicacion_actual,
            'tarja' => $shipment->fecha_tarja,
            'almacen' => $shipment->almacen_direccionamiento,
            'endoso' => ucfirst(str_replace('_', ' ', (string) $shipment->estado_endoso)),
            'comercial' => $shipment->comercial?->name,
            'customer' => $shipment->customerService?->name,
            'operador' => $shipment->operador?->name,
        ]);
    }

    /** Estado financiero por RO (venta, costo y margen en USD). */
    public static function financialColumns(): array
    {
        return [
            'ro' => ['label' => 'RO', 'width' => 16],
            'ot' => ['label' => 'OT', 'width' => 16],
            'cliente' => ['label' => 'Cliente', 'width' => 32],
            'comercial' => ['label' => 'Comercial', 'width' => 18],
            'tipo' => ['label' => 'Tipo', 'width' => 9],
            'etd' => ['label' => 'ETD', 'format' => 'date'],
            'estado' => ['label' => 'Estado', 'width' => 20],
            'credito' => ['label' => 'Credito', 'width' => 16],
            'tipo_cambio' => ['label' => 'TC (S/ por US$)', 'format' => 'number'],
            'venta_usd' => ['label' => 'Venta USD', 'format' => 'money', 'total' => true],
            'costo_usd' => ['label' => 'Costo USD', 'format' => 'money', 'total' => true],
            'margen_usd' => ['label' => 'Margen USD', 'format' => 'money', 'total' => true],
            'margen_pct' => ['label' => 'Margen %', 'format' => 'percent'],
            'costos_pendientes' => ['label' => 'Costos por pagar', 'format' => 'integer', 'total' => true],
            'observacion' => ['label' => 'Observacion', 'width' => 34],
        ];
    }

    public static function financialRows(Collection $shipments): Collection
    {
        return $shipments->map(function (Shipment $shipment) {
            $financials = ShipmentFinancials::for($shipment);

            return [
                'ro' => $shipment->routing_order,
                'ot' => $shipment->nro_orden_trabajo,
                'cliente' => $shipment->client?->razon_social,
                'comercial' => $shipment->comercial?->name ?? 'Venta directa',
                'tipo' => $shipment->tipo_carga,
                'etd' => $shipment->fecha_etd,
                'estado' => $shipment->estado_actual,
                'credito' => $shipment->creditLabel().($shipment->credito_forzado ? ' (forzado)' : ''),
                'tipo_cambio' => $shipment->tipo_cambio !== null ? (float) $shipment->tipo_cambio : null,
                'venta_usd' => $financials->salesUsd,
                'costo_usd' => $financials->costsUsd,
                'margen_usd' => $financials->marginUsd(),
                'margen_pct' => $financials->marginPercent(),
                'costos_pendientes' => $shipment->provisions->where('estado', 'pendiente')->count(),
                'observacion' => $financials->isMissingExchangeRate() ? 'Montos en PEN sin tipo de cambio: margen no calculable.' : null,
            ];
        });
    }

    public static function rankingColumns(): array
    {
        return [
            'position' => ['label' => '#', 'format' => 'integer', 'width' => 6],
            'name' => ['label' => 'Comercial', 'width' => 26],
            'shipments' => ['label' => 'RO', 'format' => 'integer', 'total' => true],
            'sales' => ['label' => 'Venta USD', 'format' => 'money', 'total' => true],
            'margin' => ['label' => 'Margen USD', 'format' => 'money', 'total' => true],
            'margin_percent' => ['label' => 'Margen %', 'format' => 'percent'],
            'incomplete' => ['label' => 'RO sin TC', 'format' => 'integer'],
        ];
    }
}
