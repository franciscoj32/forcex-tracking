<?php

namespace App\Support;

use App\Models\Shipment;

/**
 * Colores de los graficos. Cada color significa algo y se repite igual en
 * todo el sistema (FCL siempre es del mismo color, la etapa "En destino"
 * siempre es del mismo color), asi el usuario aprende a leerlos.
 *
 * Las clases estan escritas completas para que Tailwind las incluya
 * (este archivo esta en el "content" de tailwind.config.js).
 */
final class ChartPalette
{
    /** Tipo de carga. */
    public const CARGO = [
        'FCL' => ['bar' => 'bg-brand-600', 'dot' => 'bg-brand-600', 'label' => 'FCL'],
        'LCL' => ['bar' => 'bg-accent-500', 'dot' => 'bg-accent-500', 'label' => 'LCL'],
        'AEREO' => ['bar' => 'bg-violet-500', 'dot' => 'bg-violet-500', 'label' => 'Aéreo'],
    ];

    /** Etapas del recorrido (agrupan los 12 hitos). */
    public const STAGES = [
        'origen' => ['label' => 'En origen', 'bar' => 'bg-amber-500', 'dot' => 'bg-amber-500'],
        'transito' => ['label' => 'En tránsito', 'bar' => 'bg-brand-500', 'dot' => 'bg-brand-500'],
        'destino' => ['label' => 'En destino', 'bar' => 'bg-violet-500', 'dot' => 'bg-violet-500'],
        'entrega' => ['label' => 'Entrega y cierre', 'bar' => 'bg-accent-600', 'dot' => 'bg-accent-600'],
    ];

    private const STATE_STAGE = [
        'Creacion Routing Order' => 'origen',
        'Booking Confirmado' => 'origen',
        'ATD' => 'transito',
        'En transito' => 'transito',
        'Transmision Aduanas' => 'transito',
        'ETA' => 'transito',
        'Arribo a puerto' => 'destino',
        'Descarga' => 'destino',
        'Ingreso a almacen' => 'destino',
        'Retiro de almacen' => 'entrega',
        'Entregado' => 'entrega',
        'Finalizado' => 'entrega',
    ];

    /** Colores para series sin significado propio (clientes, comerciales). */
    public const SERIES = [
        'bg-brand-600',
        'bg-accent-500',
        'bg-violet-500',
        'bg-amber-500',
        'bg-sky-500',
        'bg-rose-500',
        'bg-teal-500',
        'bg-orange-500',
    ];

    public static function stageOf(?string $state): string
    {
        return self::STATE_STAGE[$state] ?? 'origen';
    }

    public static function stage(?string $state): array
    {
        return self::STAGES[self::stageOf($state)];
    }

    public static function series(int $index): string
    {
        return self::SERIES[$index % count(self::SERIES)];
    }

    /** @return array<int, string> estados en orden con su etapa */
    public static function states(): array
    {
        return Shipment::TRACKING_STATES;
    }
}
