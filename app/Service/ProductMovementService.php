<?php

namespace App\Service;

use App\Enums\ProductStatusEnum;
use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Models\Product;
use App\Models\ProductMovement;

/**
 * Responsible only for creating ProductMovement records.
 * All pricing fields must be fully resolved by CurrencyPricingHelper before calling these methods.
 */
class ProductMovementService
{
    // ─────────────────────────────────────────────────────────────────────────
    // External Purchase — direction IN, movement_type EXTERNAL_PURCHASED
    // Purchase pricing comes from the request (after CurrencyPricingHelper).
    // ─────────────────────────────────────────────────────────────────────────

    public function createExternalPurchaseMovement(Product $product, array $validated, int $userId): ProductMovement
    {
        return ProductMovement::create([
            'product_id'                             => $product->id,
            'direction'                              => StockDirectionEnum::IN->value,
            'movement_type'                          => ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value,
            // product_type is now stored on the Product model; movements no longer persist it
            'product_status'                         => ProductStatusEnum::COMPLETED->value,
            'quantity'                               => $validated['quantity'],
            'is_sold'                                => false,
            'movement_date'                          => $validated['movement_date'],
            'note'                                   => $validated['note'] ?? null,
            'created_by'                             => $userId,
            'last_updated_by'                        => $userId,

            // Purchase pricing (derived by CurrencyPricingHelper)
            'purchase_unit_price_in_usd'             => $validated['purchase_unit_price_in_usd'],
            'purchase_total_price_in_usd'            => $validated['purchase_total_price_in_usd']  ?? 0,
            'exchange_rate_from_usd_to_riel'         => $validated['exchange_rate_from_usd_to_riel'],
            'purchase_unit_price_in_riel'            => $validated['purchase_unit_price_in_riel']  ?? 0,
            'purchase_total_price_in_riel'           => $validated['purchase_total_price_in_riel'] ?? 0,
            'exchange_rate_from_riel_to_usd'         => $validated['exchange_rate_from_riel_to_usd'] ?? 0,

            // Selling pricing (unit only — no totals)
            'selling_unit_price_in_usd'              => $validated['selling_unit_price_in_usd'],
            'selling_unit_price_in_riel'             => $validated['selling_unit_price_in_riel']  ?? 0,
            'selling_exchange_rate_from_usd_to_riel' => $validated['selling_exchange_rate_from_usd_to_riel'],
            'selling_exchange_rate_from_riel_to_usd' => $validated['selling_exchange_rate_from_riel_to_usd'] ?? 0,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal Production — direction IN, movement_type INTERNAL_PRODUCED
    // All purchase price fields are forced to 0 (produced internally).
    // ─────────────────────────────────────────────────────────────────────────

    public function createInternalProductionMovement(Product $product, array $validated, int $userId): ProductMovement
    {
        return ProductMovement::create([
            'product_id'                             => $product->id,
            'direction'                              => StockDirectionEnum::IN->value,
            'movement_type'                          => ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value,
            // product_type is now stored on the Product model; movements no longer persist it
            'product_status'                         => $validated['product_status'],
            'quantity'                               => $validated['quantity'],
            'is_sold'                                => false,
            'movement_date'                          => $validated['movement_date'],
            'note'                                   => $validated['note'] ?? null,
            'created_by'                             => $userId,
            'last_updated_by'                        => $userId,

            // Purchase pricing forced to 0 — produced internally, no cost input
            'purchase_unit_price_in_usd'             => 0,
            'purchase_total_price_in_usd'            => 0,
            'exchange_rate_from_usd_to_riel'         => 0,
            'purchase_unit_price_in_riel'            => 0,
            'purchase_total_price_in_riel'           => 0,
            'exchange_rate_from_riel_to_usd'         => 0,

            // Selling pricing (unit only — no totals)
            'selling_unit_price_in_usd'              => $validated['selling_unit_price_in_usd'],
            'selling_unit_price_in_riel'             => $validated['selling_unit_price_in_riel']  ?? 0,
            'selling_exchange_rate_from_usd_to_riel' => $validated['selling_exchange_rate_from_usd_to_riel'],
            'selling_exchange_rate_from_riel_to_usd' => $validated['selling_exchange_rate_from_riel_to_usd'] ?? 0,
        ]);
    }
}
