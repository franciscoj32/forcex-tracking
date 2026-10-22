<?php

namespace App\Support;

use App\Models\Shipment;

/**
 * Resumen financiero de una RO.
 *
 *   Margen (USD) = Tarifas de venta (USD) - Costos de proveedores (USD)
 *
 * Los montos en PEN se convierten con el tipo de cambio congelado en la RO
 * (shipments.tipo_cambio). Si hay montos en PEN y la RO no tiene tipo de
 * cambio, el margen no se puede calcular y se marca como incompleto.
 */
final class ShipmentFinancials
{
    public function __construct(
        public readonly array $salesByCurrency,
        public readonly array $costsByCurrency,
        public readonly ?float $salesUsd,
        public readonly ?float $costsUsd,
        public readonly ?float $exchangeRate,
    ) {
    }

    public static function for(Shipment $shipment): self
    {
        $shipment->loadMissing(['rates', 'provisions']);
        $exchangeRate = $shipment->tipo_cambio !== null ? (float) $shipment->tipo_cambio : null;

        [$salesByCurrency, $salesUsd] = self::sum($shipment->rates, $exchangeRate);
        [$costsByCurrency, $costsUsd] = self::sum($shipment->provisions, $exchangeRate);

        return new self($salesByCurrency, $costsByCurrency, $salesUsd, $costsUsd, $exchangeRate);
    }

    public function marginUsd(): ?float
    {
        if ($this->salesUsd === null || $this->costsUsd === null) {
            return null;
        }

        return round($this->salesUsd - $this->costsUsd, 2);
    }

    /** Margen sobre la venta, en porcentaje. */
    public function marginPercent(): ?float
    {
        $margin = $this->marginUsd();

        if ($margin === null || ! $this->salesUsd) {
            return null;
        }

        return round(($margin / $this->salesUsd) * 100, 1);
    }

    /** Hay montos en PEN pero la RO no tiene tipo de cambio. */
    public function isMissingExchangeRate(): bool
    {
        return $this->marginUsd() === null;
    }

    public function hasSales(): bool
    {
        return array_sum($this->salesByCurrency) > 0;
    }

    /** @return array{0: array<string, float>, 1: ?float} */
    private static function sum(iterable $lines, ?float $exchangeRate): array
    {
        $byCurrency = [];
        $usd = 0.0;
        $convertible = true;

        foreach ($lines as $line) {
            $amount = (float) $line->monto;
            $currency = $line->moneda ?: 'USD';
            $byCurrency[$currency] = ($byCurrency[$currency] ?? 0) + $amount;

            $converted = Money::toUsd($amount, $currency, $exchangeRate);

            if ($converted === null) {
                $convertible = false;
            } else {
                $usd += $converted;
            }
        }

        return [$byCurrency, $convertible ? round($usd, 2) : null];
    }
}
