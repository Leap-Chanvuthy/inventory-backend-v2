<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class CurrencyPricingHelper
{
    /**
     * Fill missing RMPurchasingTransaction currency fields on the request.
     *
     * Supports computing:
     * - `total_value_*` from `unit_price_*` * `quantity`
     * - `unit_price_*` from `total_value_*` / `quantity`
     * - currency conversion using either exchange rate direction
     * - inverse exchange rate automatically
     */
    public static function fillRMPurchasingCurrencyFields(Request $request, array $options = []): void
    {
        $usdDecimals = (int) ($options['usd_decimals'] ?? 4);
        $rielDecimals = (int) ($options['riel_decimals'] ?? 0);
        $rateDecimals = (int) ($options['rate_decimals'] ?? 8);

        $quantity = self::toNumericOrNull($request->input('quantity'));
        if ($quantity !== null && $quantity <= 0) {
            $quantity = null;
        }

        $unitUsd = self::toNumericOrNull($request->input('unit_price_in_usd'));
        $totalUsd = self::toNumericOrNull($request->input('total_value_in_usd'));

        $unitRiel = self::toNumericOrNull($request->input('unit_price_in_riel'));
        $totalRiel = self::toNumericOrNull($request->input('total_value_in_riel'));

        $usdToRiel = self::toNumericOrNull($request->input('exchange_rate_from_usd_to_riel'));
        $rielToUsd = self::toNumericOrNull($request->input('exchange_rate_from_riel_to_usd'));

        // Prefer USD->Riel if present; else derive it from Riel->USD.
        if (($usdToRiel === null || $usdToRiel <= 0) && $rielToUsd !== null && $rielToUsd > 0) {
            $usdToRiel = round(1 / $rielToUsd, $rateDecimals);
            $request->merge(['exchange_rate_from_usd_to_riel' => $usdToRiel]);
        }

        // Prefer Riel->USD if present; else derive it from USD->Riel.
        if (($rielToUsd === null || $rielToUsd <= 0) && $usdToRiel !== null && $usdToRiel > 0) {
            $rielToUsd = round(1 / $usdToRiel, $rateDecimals);
            $request->merge(['exchange_rate_from_riel_to_usd' => $rielToUsd]);
        }

        // If quantity is available, ensure unit/total pairs are consistent.
        if ($quantity !== null) {
            // Prefer unit price as the source of truth when present.
            if ($unitUsd !== null) {
                $totalUsd = round($unitUsd * $quantity, $usdDecimals);
                $request->merge(['total_value_in_usd' => $totalUsd]);
            } elseif ($totalUsd !== null) {
                $unitUsd = round($totalUsd / $quantity, $usdDecimals);
                $request->merge(['unit_price_in_usd' => $unitUsd]);
            }

            if ($unitRiel !== null) {
                $totalRiel = round($unitRiel * $quantity, $rielDecimals);
                $request->merge(['total_value_in_riel' => $totalRiel]);
            } elseif ($totalRiel !== null) {
                $unitRiel = round($totalRiel / $quantity, $rielDecimals);
                $request->merge(['unit_price_in_riel' => $unitRiel]);
            }
        }

        // Convert USD -> Riel when we have rate.
        if ($usdToRiel !== null && $usdToRiel > 0) {
            if ($unitUsd !== null && $unitRiel === null) {
                $unitRiel = round($unitUsd * $usdToRiel, $rielDecimals);
                $request->merge(['unit_price_in_riel' => $unitRiel]);
            }

            if ($totalUsd !== null && $totalRiel === null) {
                $totalRiel = round($totalUsd * $usdToRiel, $rielDecimals);
                $request->merge(['total_value_in_riel' => $totalRiel]);
            }
        }

        // Convert Riel -> USD when we have rate.
        if ($rielToUsd !== null && $rielToUsd > 0) {
            if ($unitRiel !== null && $unitUsd === null) {
                $unitUsd = round($unitRiel * $rielToUsd, $usdDecimals);
                $request->merge(['unit_price_in_usd' => $unitUsd]);
            }

            if ($totalRiel !== null && $totalUsd === null) {
                $totalUsd = round($totalRiel * $rielToUsd, $usdDecimals);
                $request->merge(['total_value_in_usd' => $totalUsd]);
            }
        }
    }

    private static function toNumericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}
