<?php

namespace App\Services\Alerts;

use Carbon\CarbonImmutable;

/**
 * Semaforo de las alertas con plazo:
 * - Roja (danger):     24 h o menos, o vencida.
 * - Amarilla (warning): de 48 h a 24 h.
 * - Verde (info):      de 5 dias a 48 h.
 * - Mas de 5 dias:     no se muestra en el Inicio.
 */
final class AlertLevel
{
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const DANGER = 'danger';

    /** Plazo conocido pero lejano: solo se muestra en la ficha como cuenta regresiva. */
    public const NONE = 'none';

    public const RANK = [self::NONE => 0, self::INFO => 1, self::WARNING => 2, self::DANGER => 3];

    public const LABELS = [self::NONE => 'A tiempo', self::INFO => 'Preventiva', self::WARNING => 'Advertencia', self::DANGER => 'Crítica'];

    /** Clases de color por nivel (barra, fondo suave, texto). */
    public const STYLES = [
        self::DANGER => ['bar' => 'bg-rose-600', 'soft' => 'bg-rose-50', 'text' => 'text-rose-700'],
        self::WARNING => ['bar' => 'bg-amber-500', 'soft' => 'bg-amber-50', 'text' => 'text-amber-700'],
        self::INFO => ['bar' => 'bg-green-600', 'soft' => 'bg-green-50', 'text' => 'text-green-700'],
        self::NONE => ['bar' => 'bg-slate-300', 'soft' => 'bg-slate-50', 'text' => 'text-slate-500'],
    ];

    public static function style(string $level, string $part): string
    {
        return self::STYLES[$level][$part] ?? self::STYLES[self::NONE][$part];
    }

    /** null = falta mas del margen preventivo. */
    public static function for(CarbonImmutable $dueAt, CarbonImmutable $now, AlertThresholds $limits): ?string
    {
        $hoursLeft = $now->diffInMinutes($dueAt, false) / 60; // negativo = vencido

        return match (true) {
            $hoursLeft <= $limits->criticalHours => self::DANGER,
            $hoursLeft <= $limits->warningHours => self::WARNING,
            $hoursLeft <= $limits->preventiveDays * 24 => self::INFO,
            default => null,
        };
    }

    public static function isHigher(string $level, string $than): bool
    {
        return (self::RANK[$level] ?? 0) > (self::RANK[$than] ?? 0);
    }
}
