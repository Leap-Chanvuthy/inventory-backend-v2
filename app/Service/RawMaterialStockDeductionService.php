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

    public function validateSufficientStock(array $bomItems, ?string $asOfMovementDate = null): array
    {
        $shortfalls = [];

        foreach ($bomItems as $item) {
            $rawMaterialId  = (int) $item['raw_material_id'];
            $requiredQty    = (float) $item['quantity'];

            $rawMaterial = RawMaterial::findOrFail($rawMaterialId);

            $availableQty = $this->getAvailableStock($rawMaterialId, $asOfMovementDate);

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
    // Creates PRODUCTION_RECEIPT OUT movements and marks consumed IN batches
    // with in_used = true (including partially consumed batches).
    // ─────────────────────────────────────────────────────────────────────────

    public function deductStock(
        array  $bomItems,
        int    $productId,
        int    $userId,
        string $movementDate,
        ?string $referenceToken = null
    ): void {
        foreach ($bomItems as $item) {
            $rawMaterialId = (int) $item['raw_material_id'];
            $remaining     = (float) $item['quantity'];

            $rawMaterial = RawMaterial::findOrFail($rawMaterialId);

            $inMovements = $this->getOrderedInMovements($rawMaterialId, $rawMaterial->production_method, $movementDate);

            foreach ($inMovements as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $inMovement = $batch['movement'];
                $batchAvailable = (float) $batch['available_quantity'];
                if ($batchAvailable <= 0) {
                    continue;
                }
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
                    'note'                         => $this->buildConsumptionNote($productId, $referenceToken),
                ]);

                // Mark batch as used when any quantity is consumed.
                if ($consume > 0) {
                    $inMovement->update(['in_used' => true]);
                }

                $remaining -= $consume;
            }
        }
    }

    /**
     * Delete reorder-created OUT movements by token and return affected raw material IDs.
     */
    public function deleteReorderConsumptionMovementsByToken(string $referenceToken): array
    {
        $movements = RMStockMovement::where('direction', StockDirectionEnum::OUT->value)
            ->where('movement_type', RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value)
            ->where('note', 'like', '%' . $referenceToken . '%')
            ->get(['id', 'raw_material_id']);

        if ($movements->isEmpty()) {
            return [];
        }

        $rawMaterialIds = $movements->pluck('raw_material_id')->unique()->values()->all();

        RMStockMovement::whereIn('id', $movements->pluck('id')->all())->delete();

        return $rawMaterialIds;
    }

    /**
     * Recalculate in_used flags after movement replacement to keep stock state consistent.
     */
    public function rebuildInUsedFlags(array $rawMaterialIds): void
    {
        foreach (array_unique(array_map('intval', $rawMaterialIds)) as $rawMaterialId) {
            if ($rawMaterialId <= 0) {
                continue;
            }

            // Reset all IN rows first, then mark used rows again.
            RMStockMovement::where('raw_material_id', $rawMaterialId)
                ->where('direction', StockDirectionEnum::IN->value)
                ->update(['in_used' => false]);

            $remainingOut = (float) RMStockMovement::where('raw_material_id', $rawMaterialId)
                ->where('direction', StockDirectionEnum::OUT->value)
                ->where('movement_type', RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value)
                ->sum('quantity');

            if ($remainingOut <= 0) {
                continue;
            }

            $rawMaterial = RawMaterial::find($rawMaterialId);
            $methodValue = is_object($rawMaterial->production_method) ? $rawMaterial->production_method->value : (string) $rawMaterial->production_method;
            $order = strtoupper($methodValue) === 'LIFO' ? 'desc' : 'asc';

            $inRows = RMStockMovement::where('raw_material_id', $rawMaterialId)
                ->where('direction', StockDirectionEnum::IN->value)
                ->orderBy('movement_date', $order)
                ->orderBy('id', $order)
                ->get(['id', 'quantity']);

            foreach ($inRows as $inRow) {
                if ($remainingOut <= 0) {
                    break;
                }

                $inQty = (float) $inRow->quantity;
                $consume = min($inQty, $remainingOut);
                if ($consume > 0) {
                    RMStockMovement::where('id', $inRow->id)->update(['in_used' => true]);
                    $remainingOut -= $consume;
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sum available (not fully consumed) IN stock for a raw material.
     */
    private function getAvailableStock(int $rawMaterialId, ?string $asOfMovementDate = null): float
    {
        // Compute net available stock as total IN minus total OUT. When an
        // as-of date is supplied, only movements on/before that moment are
        // considered to avoid consuming future stock.
        $totalInQuery = RMStockMovement::where('raw_material_id', $rawMaterialId)
            ->where('direction', StockDirectionEnum::IN->value);
        $this->applyAsOfMovementDateFilter($totalInQuery, $asOfMovementDate);
        $totalIn = $totalInQuery->sum('quantity');

        $totalOutQuery = RMStockMovement::where('raw_material_id', $rawMaterialId)
            ->where('direction', StockDirectionEnum::OUT->value);
        $this->applyAsOfMovementDateFilter($totalOutQuery, $asOfMovementDate);
        $totalOut = $totalOutQuery->sum('quantity');

        return max(0, (float) $totalIn - (float) $totalOut);
    }

    /**
     * Return IN movements ordered by FIFO (oldest first) or LIFO (newest first),
     * with reconstructed remaining quantity per batch based on current OUT totals.
     */
    private function getOrderedInMovements(int $rawMaterialId, mixed $productionMethod, ?string $asOfMovementDate = null)
    {
        $methodValue = is_object($productionMethod) ? $productionMethod->value : (string) $productionMethod;

        $order = strtoupper($methodValue) === 'LIFO' ? 'desc' : 'asc';

        $inMovementsQuery = RMStockMovement::where('raw_material_id', $rawMaterialId)
            ->where('direction', StockDirectionEnum::IN->value)
            ->orderBy('movement_date', $order)
            ->orderBy('id', $order);
        $this->applyAsOfMovementDateFilter($inMovementsQuery, $asOfMovementDate);
        $inMovements = $inMovementsQuery->get();

        $remainingOutQuery = RMStockMovement::where('raw_material_id', $rawMaterialId)
            ->where('direction', StockDirectionEnum::OUT->value)
            ->where('movement_type', RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value);
        $this->applyAsOfMovementDateFilter($remainingOutQuery, $asOfMovementDate);
        $remainingOut = (float) $remainingOutQuery->sum('quantity');

        $batches = [];
        foreach ($inMovements as $inMovement) {
            $inQty = (float) $inMovement->quantity;
            $consumed = min($inQty, $remainingOut);
            $available = $inQty - $consumed;
            $remainingOut -= $consumed;

            if ($available > 0) {
                $batches[] = [
                    'movement' => $inMovement,
                    'available_quantity' => $available,
                ];
            }
        }

        return collect($batches);
    }

    private function buildConsumptionNote(int $productId, ?string $referenceToken = null): string
    {
        $note = "Consumed for product ID {$productId}";

        if (!empty($referenceToken)) {
            $note .= " | {$referenceToken}";
        }

        return $note;
    }

    private function applyAsOfMovementDateFilter($query, ?string $asOfMovementDate): void
    {
        if (empty($asOfMovementDate)) {
            return;
        }

        $query->where(function ($q) use ($asOfMovementDate) {
            $q->whereNull('movement_date')
                ->orWhere('movement_date', '<=', $asOfMovementDate);
        });
    }
}
