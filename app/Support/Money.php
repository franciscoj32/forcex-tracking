<?php

namespace App\Support;

/**
 * Utilidades de moneda. El tipo de cambio se expresa como PEN por 1 USD.
 */
final class Money
{
    public const SYMBOLS = ['USD' => 'US$', 'PEN' => 'S/'];

    /** Convierte un monto a USD. Devuelve null si es PEN y no hay tipo de cambio. */
    public static function toUsd(float $amount, string $currency, ?float $exchangeRate): ?float
    {
        if ($currency === 'USD') {
            return $amount;
        }

        if (! $exchangeRate || $exchangeRate <= 0) {
            return null;
        }

        return $amount / $exchangeRate;
    }

    public static function format(?float $amount, string $currency = 'USD'): string
    {
        if ($amount === null) {
            return '-';
        }

        return (self::SYMBOLS[$currency] ?? $currency).' '.number_format($amount, 2);
    }

    /**
     * Formatea totales agrupados por moneda: ['USD' => 100, 'PEN' => 50]
     * -> "US$ 100.00 + S/ 50.00".
     */
    public static function formatTotals(array $totalsByCurrency): string
    {
        $parts = collect($totalsByCurrency)
            ->filter(fn ($amount) => (float) $amount !== 0.0)
            ->map(fn ($amount, $currency) => self::format((float) $amount, $currency));

        return $parts->isEmpty() ? self::format(0.0, 'USD') : $parts->join(' + ');
    }
}
