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

    /**
     * Fill missing product movement currency fields on the request.
     *
     * Purchase side:
     *   - Input:  purchase_unit_price_in_usd, quantity, exchange_rate_from_usd_to_riel
     *   - Derives: purchase_total_price_in_usd, purchase_unit_price_in_riel,
     *              purchase_total_price_in_riel, exchange_rate_from_riel_to_usd
     *
     * Selling side (unit only — no totals):
     *   - Input:  selling_unit_price_in_usd, selling_exchange_rate_from_usd_to_riel
     *   - Derives: selling_unit_price_in_riel, selling_exchange_rate_from_riel_to_usd
     */
    public static function fillProductPurchasingCurrencyFields(Request $request, array $options = []): void
    {
        $usdDecimals  = (int) ($options['usd_decimals']  ?? 4);
        $rielDecimals = (int) ($options['riel_decimals'] ?? 0);
        $rateDecimals = (int) ($options['rate_decimals'] ?? 8);

        // ── Purchase side ────────────────────────────────────────────────────
        $quantity       = self::toNumericOrNull($request->input('quantity'));
        if ($quantity !== null && $quantity <= 0) {
            $quantity = null;
        }

        $purchaseUnitUsd   = self::toNumericOrNull($request->input('purchase_unit_price_in_usd'));
        $purchaseTotalUsd  = self::toNumericOrNull($request->input('purchase_total_price_in_usd'));
        $purchaseUnitRiel  = self::toNumericOrNull($request->input('purchase_unit_price_in_riel'));
        $purchaseTotalRiel = self::toNumericOrNull($request->input('purchase_total_price_in_riel'));
        $usdToRiel         = self::toNumericOrNull($request->input('exchange_rate_from_usd_to_riel'));
        $rielToUsd         = self::toNumericOrNull($request->input('exchange_rate_from_riel_to_usd'));

        // Derive inverse exchange rates
        if (($usdToRiel === null || $usdToRiel <= 0) && $rielToUsd !== null && $rielToUsd > 0) {
            $usdToRiel = round(1 / $rielToUsd, $rateDecimals);
            $request->merge(['exchange_rate_from_usd_to_riel' => $usdToRiel]);
        }
        if (($rielToUsd === null || $rielToUsd <= 0) && $usdToRiel !== null && $usdToRiel > 0) {
            $rielToUsd = round(1 / $usdToRiel, $rateDecimals);
            $request->merge(['exchange_rate_from_riel_to_usd' => $rielToUsd]);
        }

        // Compute purchase totals from unit price × qty
        if ($quantity !== null) {
            if ($purchaseUnitUsd !== null) {
                $purchaseTotalUsd = round($purchaseUnitUsd * $quantity, $usdDecimals);
                $request->merge(['purchase_total_price_in_usd' => $purchaseTotalUsd]);
            } elseif ($purchaseTotalUsd !== null) {
                $purchaseUnitUsd = round($purchaseTotalUsd / $quantity, $usdDecimals);
                $request->merge(['purchase_unit_price_in_usd' => $purchaseUnitUsd]);
            }

            if ($purchaseUnitRiel !== null) {
                $purchaseTotalRiel = round($purchaseUnitRiel * $quantity, $rielDecimals);
                $request->merge(['purchase_total_price_in_riel' => $purchaseTotalRiel]);
            } elseif ($purchaseTotalRiel !== null) {
                $purchaseUnitRiel = round($purchaseTotalRiel / $quantity, $rielDecimals);
                $request->merge(['purchase_unit_price_in_riel' => $purchaseUnitRiel]);
            }
        }

        // Convert purchase USD → Riel
        if ($usdToRiel !== null && $usdToRiel > 0) {
            if ($purchaseUnitUsd !== null && $purchaseUnitRiel === null) {
                $purchaseUnitRiel = round($purchaseUnitUsd * $usdToRiel, $rielDecimals);
                $request->merge(['purchase_unit_price_in_riel' => $purchaseUnitRiel]);
            }
            if ($purchaseTotalUsd !== null && $purchaseTotalRiel === null) {
                $purchaseTotalRiel = round($purchaseTotalUsd * $usdToRiel, $rielDecimals);
                $request->merge(['purchase_total_price_in_riel' => $purchaseTotalRiel]);
            }
        }

        // Convert purchase Riel → USD
        if ($rielToUsd !== null && $rielToUsd > 0) {
            if ($purchaseUnitRiel !== null && $purchaseUnitUsd === null) {
                $purchaseUnitUsd = round($purchaseUnitRiel * $rielToUsd, $usdDecimals);
                $request->merge(['purchase_unit_price_in_usd' => $purchaseUnitUsd]);
            }
            if ($purchaseTotalRiel !== null && $purchaseTotalUsd === null) {
                $purchaseTotalUsd = round($purchaseTotalRiel * $rielToUsd, $usdDecimals);
                $request->merge(['purchase_total_price_in_usd' => $purchaseTotalUsd]);
            }
        }

        // ── Selling side (unit price only — no totals) ────────────────────────
        $sellUnitUsd      = self::toNumericOrNull($request->input('selling_unit_price_in_usd'));
        $sellUnitRiel     = self::toNumericOrNull($request->input('selling_unit_price_in_riel'));
        $sellUsdToRiel    = self::toNumericOrNull($request->input('selling_exchange_rate_from_usd_to_riel'));
        $sellRielToUsd    = self::toNumericOrNull($request->input('selling_exchange_rate_from_riel_to_usd'));

        // Derive inverse selling exchange rates
        if (($sellUsdToRiel === null || $sellUsdToRiel <= 0) && $sellRielToUsd !== null && $sellRielToUsd > 0) {
            $sellUsdToRiel = round(1 / $sellRielToUsd, $rateDecimals);
            $request->merge(['selling_exchange_rate_from_usd_to_riel' => $sellUsdToRiel]);
        }
        if (($sellRielToUsd === null || $sellRielToUsd <= 0) && $sellUsdToRiel !== null && $sellUsdToRiel > 0) {
            $sellRielToUsd = round(1 / $sellUsdToRiel, $rateDecimals);
            $request->merge(['selling_exchange_rate_from_riel_to_usd' => $sellRielToUsd]);
        }

        // Derive selling unit price in Riel from USD
        if ($sellUsdToRiel !== null && $sellUsdToRiel > 0 && $sellUnitUsd !== null && $sellUnitRiel === null) {
            $sellUnitRiel = round($sellUnitUsd * $sellUsdToRiel, $rielDecimals);
            $request->merge(['selling_unit_price_in_riel' => $sellUnitRiel]);
        }

        // Derive selling unit price in USD from Riel
        if ($sellRielToUsd !== null && $sellRielToUsd > 0 && $sellUnitRiel !== null && $sellUnitUsd === null) {
            $sellUnitUsd = round($sellUnitRiel * $sellRielToUsd, $usdDecimals);
            $request->merge(['selling_unit_price_in_usd' => $sellUnitUsd]);
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
