<?php

namespace App\Service;

use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Models\RawMaterial;
use App\Models\RMStockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Handles FIFO / LIFO raw material stock check and deduction
 * when creating an internally manufactured product.
 */
class RawMaterialStockDeductionService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Public: validate that stock is sufficient for every BOM item.
    // Returns an array of shortfall details; empty array = all stock OK.
    // ─────────────────────────────────────────────────────────────────────────

    public function validateSufficientStock(array $bomItems): array
    {
        $shortfalls = [];

        foreach ($bomItems as $item) {
            $rawMaterialId  = (int) $item['raw_material_id'];
            $requiredQty    = (float) $item['quantity'];

            $rawMaterial = RawMaterial::findOrFail($rawMaterialId);

            $availableQty = $this->getAvailableStock($rawMaterialId, $rawMaterial->production_method);

            if ($availableQty < $requiredQty) {
                $shortfalls[] = [
                    'raw_material_id'   => $rawMaterialId,
                    'material_name'     => $rawMaterial->material_name,
                    'material_sku_code' => $rawMaterial->material_sku_code,
                    'required_qty'      => $requiredQty,
                    'available_qty'     => $availableQty,
                    'shortfall_qty'     => round($requiredQty - $availableQty, 4),
                ];
            }
        }

        return $shortfalls;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public: deduct stock for all BOM items (must run inside a DB transaction).
    // Creates PRODUCTION_RECEIPT OUT movements and marks fully consumed
    // IN batches with in_used = true.
    // ─────────────────────────────────────────────────────────────────────────

    public function deductStock(
        array  $bomItems,
        int    $productId,
        int    $userId,
        string $movementDate
    ): void {
        foreach ($bomItems as $item) {
            $rawMaterialId = (int) $item['raw_material_id'];
            $remaining     = (float) $item['quantity'];

            $rawMaterial = RawMaterial::findOrFail($rawMaterialId);

            $inMovements = $this->getOrderedInMovements($rawMaterialId, $rawMaterial->production_method);

            foreach ($inMovements as $inMovement) {
                if ($remaining <= 0) {
                    break;
                }

                $batchAvailable = (float) $inMovement->quantity;
                $consume        = min($batchAvailable, $remaining);

                // Create PRODUCTION_RECEIPT OUT record for the consumed portion
                RMStockMovement::create([
                    'raw_material_id'              => $rawMaterialId,
                    'quantity'                     => $consume,
                    'direction'                    => StockDirectionEnum::OUT->value,
                    'movement_type'                => RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
                    'in_used'                      => false,
                    'movement_date'                => $movementDate,
                    'unit_price_in_usd'            => $inMovement->unit_price_in_usd,
                    'total_value_in_usd'           => round($inMovement->unit_price_in_usd * $consume, 4),
                    'exchange_rate_from_usd_to_riel' => $inMovement->exchange_rate_from_usd_to_riel,
                    'unit_price_in_riel'           => $inMovement->unit_price_in_riel,
                    'total_value_in_riel'          => round($inMovement->unit_price_in_riel * $consume, 0),
                    'exchange_rate_from_riel_to_usd' => $inMovement->exchange_rate_from_riel_to_usd,
                    'created_by'                   => $userId,
                    'last_updated_by'              => $userId,
                    'note'                         => "Consumed for product ID {$productId}",
                ]);

                // Mark batch as fully consumed when the entire qty is used
                if ($consume >= $batchAvailable) {
                    $inMovement->update(['in_used' => true]);
                }

                $remaining -= $consume;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sum available (not fully consumed) IN stock for a raw material.
     */
    private function getAvailableStock(int $rawMaterialId, mixed $productionMethod): float
    {
        $totalIn = RMStockMovement::where('raw_material_id', $rawMaterialId)
            ->where('direction', StockDirectionEnum::IN->value)
            ->where('in_used', false)
            ->sum('quantity');

        $totalOut = RMStockMovement::where('raw_material_id', $rawMaterialId)
            ->where('direction', StockDirectionEnum::OUT->value)
            ->sum('quantity');

        return max(0, (float) $totalIn - (float) $totalOut);
    }

    /**
     * Return IN movements ordered by FIFO (oldest first) or LIFO (newest first),
     * only considering batches not yet fully consumed.
     */
    private function getOrderedInMovements(int $rawMaterialId, mixed $productionMethod)
    {
        $methodValue = is_object($productionMethod) ? $productionMethod->value : (string) $productionMethod;

        $order = strtoupper($methodValue) === 'LIFO' ? 'desc' : 'asc';

        return RMStockMovement::where('raw_material_id', $rawMaterialId)
            ->where('direction', StockDirectionEnum::IN->value)
            ->where('in_used', false)
            ->orderBy('movement_date', $order)
            ->get();
    }
}
