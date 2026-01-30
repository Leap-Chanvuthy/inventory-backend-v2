<?php

namespace App\Helpers;

use InvalidArgumentException;

class CurrencyHelper
{
    public const USD = 'USD';
    public const KHR = 'KHR';

    /**
     * Convert an amount using an explicit exchange rate.
     *
     * The provided $exchangeRate must mean:
     *   1 $baseCurrency = $exchangeRate $targetCurrency
     *
     * Examples:
     * - base=USD, target=KHR, exchangeRate=4100 => 1 USD = 4100 KHR
     * - base=KHR, target=USD, exchangeRate=0.0002439 => 1 KHR = 0.0002439 USD
     */
    public static function convert(
        float $amount,
        string $baseCurrency,
        string $targetCurrency,
        float $exchangeRate,
        ?int $precision = 2
    ): float {
        $baseCurrency = strtoupper(trim($baseCurrency));
        $targetCurrency = strtoupper(trim($targetCurrency));

        if ($amount < 0) {
            throw new InvalidArgumentException('Amount must be >= 0');
        }

        if ($baseCurrency === '' || $targetCurrency === '') {
            throw new InvalidArgumentException('Currency code is required');
        }

        if ($baseCurrency === $targetCurrency) {
            return self::round($amount, $precision);
        }

        if ($exchangeRate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be > 0');
        }

        $converted = $amount * $exchangeRate;

        return self::round($converted, $precision);
    }

    /**
     * Convert an amount when you only have the inverse rate.
     *
     * inverseRate means:
     *   1 $targetCurrency = $inverseRate $baseCurrency
     */
    public static function convertUsingInverseRate(
        float $amount,
        string $baseCurrency,
        string $targetCurrency,
        float $inverseRate,
        ?int $precision = 2
    ): float {
        if ($inverseRate <= 0) {
            throw new InvalidArgumentException('Inverse exchange rate must be > 0');
        }

        return self::convert(
            amount: $amount,
            baseCurrency: $baseCurrency,
            targetCurrency: $targetCurrency,
            exchangeRate: 1 / $inverseRate,
            precision: $precision
        );
    }

    /** Convenience: USD -> KHR (Riel). */
    public static function usdToKhr(float $usdAmount, float $usdToKhrRate, ?int $precision = 0): float
    {
        // Riel is usually shown without decimals
        return self::convert($usdAmount, self::USD, self::KHR, $usdToKhrRate, $precision);
    }

    /** Convenience: KHR (Riel) -> USD. */
    public static function khrToUsd(float $khrAmount, float $khrToUsdRate, ?int $precision = 2): float
    {
        return self::convert($khrAmount, self::KHR, self::USD, $khrToUsdRate, $precision);
    }

    /**
     * Calculate both currencies from a base amount + a single known rate.
     *
     * Example:
     * - baseCurrency=USD, rate=4100 => returns ['USD'=>amount, 'KHR'=>amount*4100]
     */
    public static function dual(
        float $amount,
        string $baseCurrency,
        string $targetCurrency,
        float $exchangeRate,
        ?int $basePrecision = 2,
        ?int $targetPrecision = 0
    ): array {
        return [
            strtoupper(trim($baseCurrency)) => self::round($amount, $basePrecision),
            strtoupper(trim($targetCurrency)) => self::convert($amount, $baseCurrency, $targetCurrency, $exchangeRate, $targetPrecision),
        ];
    }

    /**
     * Helper for your RawMaterial style fields.
     *
     * Given quantity + unit price (in base currency) + base->target exchange rate,
     * returns unit + total values in both currencies.
     */
    public static function computeUnitAndTotals(
        float $quantity,
        float $unitPrice,
        string $baseCurrency,
        string $targetCurrency,
        float $exchangeRate,
        ?int $basePrecision = 2,
        ?int $targetPrecision = 0
    ): array {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity must be >= 0');
        }
        if ($unitPrice < 0) {
            throw new InvalidArgumentException('Unit price must be >= 0');
        }

        $totalBase = $quantity * $unitPrice;

        return [
            'quantity' => $quantity,

            'unit_price' => [
                strtoupper(trim($baseCurrency)) => self::round($unitPrice, $basePrecision),
                strtoupper(trim($targetCurrency)) => self::convert($unitPrice, $baseCurrency, $targetCurrency, $exchangeRate, $targetPrecision),
            ],

            'total_value' => [
                strtoupper(trim($baseCurrency)) => self::round($totalBase, $basePrecision),
                strtoupper(trim($targetCurrency)) => self::convert($totalBase, $baseCurrency, $targetCurrency, $exchangeRate, $targetPrecision),
            ],

            'exchange_rate' => [
                'base' => strtoupper(trim($baseCurrency)),
                'target' => strtoupper(trim($targetCurrency)),
                'rate' => $exchangeRate,
            ],
        ];
    }

    private static function round(float $value, ?int $precision): float
    {
        if ($precision === null) {
            return $value;
        }

        return round($value, $precision);
    }
}


// Example usage (e.g. in your service when saving raw materials):
// CurrencyHelper::computeUnitAndTotals(quantity: 5, unitPrice: 2.5, baseCurrency: 'USD', targetCurrency: 'KHR', exchangeRate: 4100);c