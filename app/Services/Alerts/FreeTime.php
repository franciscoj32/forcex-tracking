<?php

namespace App\Services\Alerts;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dias libres en dias calendario: el dia de inicio cuenta como dia 1.
 * Ej.: descarga 10/10 con 7 dias libres -> libre hasta 16/10 23:59; el 17/10 se cobra.
 */
final class FreeTime
{
    /** Ultimo momento libre. Con 0 dias libres se cobra desde el inicio del dia 1. */
    public static function endsAt(CarbonInterface $start, int $freeDays): CarbonImmutable
    {
        $day1 = CarbonImmutable::instance($start)->startOfDay();

        return $freeDays <= 0 ? $day1 : $day1->addDays($freeDays - 1)->endOfDay();
    }

    /** Numero de dia en curso (el dia de inicio es el dia 1). */
    public static function dayNumber(CarbonInterface $start, CarbonInterface $now): int
    {
        $day1 = CarbonImmutable::instance($start)->startOfDay();

        return (int) $day1->diffInDays(CarbonImmutable::instance($now)->startOfDay(), false) + 1;
    }
}
