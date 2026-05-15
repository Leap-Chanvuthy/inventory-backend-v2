<?php

namespace App\Service;

use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Models\ProductRawMaterial;
use App\Models\ProductReorder;
use App\Models\ReorderProductRawMaterial;
use App\Models\RMStockMovement;

class ManufacturingService
{
    public function __construct(
        protected RawMaterialStockDeductionService $rawMaterialStockDeductionService
    ) {
    }

    /**
     * Normalize BOM payload to the per-unit contract.
     * Accepts legacy `quantity` as fallback for backward compatibility.
     */
    public function normalizeBomItems(array $bomItems): array
    {
        return collect($bomItems)
            ->map(function (array $item) {
                $quantityPerUnit = (float) ($item['quantity_per_unit'] ?? $item['quantity'] ?? 0);
                $scrapPercentage = (float) ($item['scrap_percentage'] ?? 0);

                return [
                    'raw_material_id' => (int) ($item['raw_material_id'] ?? 0),
                    'quantity_per_unit' => round($quantityPerUnit, 4),
                    'scrap_percentage' => round(max(0, min(100, $scrapPercentage)), 4),
                ];
            })
            ->filter(fn (array $item) => $item['raw_material_id'] > 0)
            ->values()
            ->all();
    }

    /**
     * Build per-material consumption plan for a production quantity.
     */
    public function buildConsumptionPlan(array $bomItems, float $productionQuantity): array
    {
        $normalizedBom = $this->normalizeBomItems($bomItems);
        $quantity = max(0, $productionQuantity);

        return collect($normalizedBom)
            ->map(function (array $item) use ($quantity) {
                $requiredQty = round($quantity * (float) $item['quantity_per_unit'], 4);
                $scrapQty = round(($requiredQty * (float) $item['scrap_percentage']) / 100, 4);
                $totalConsumption = round($requiredQty + $scrapQty, 4);

                return array_merge($item, [
                    'required_qty' => $requiredQty,
                    'scrap_qty' => $scrapQty,
                    'total_consumption' => $totalConsumption,
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * Validate stock for the precomputed consumption plan.
     */
    public function validateSufficientStockForPlan(array $consumptionPlan): array
    {
        $requiredItems = collect($consumptionPlan)
            ->filter(fn (array $item) => (float) ($item['total_consumption'] ?? 0) > 0)
            ->map(fn (array $item) => [
                'raw_material_id' => (int) $item['raw_material_id'],
                'quantity' => (float) $item['total_consumption'],
            ])
            ->values()
            ->all();

        if (empty($requiredItems)) {
            return [];
        }

        return $this->rawMaterialStockDeductionService->validateSufficientStock($requiredItems);
    }

    /**
     * Deduct production-consumption and scrap-loss movements from the computed plan.
     */
    public function deductStockForPlan(
        array $consumptionPlan,
        int $productId,
        int $userId,
        string $movementDate,
        ?string $referenceToken = null,
        ?int $productMovementId = null
    ): void {
        $productionReceiptItems = [];
        $scrapItems = [];

        foreach ($consumptionPlan as $item) {
            $rawMaterialId = (int) ($item['raw_material_id'] ?? 0);
            $requiredQty = (float) ($item['required_qty'] ?? 0);
            $scrapQty = (float) ($item['scrap_qty'] ?? 0);

            if ($rawMaterialId <= 0) {
                continue;
            }

            if ($requiredQty > 0) {
                $productionReceiptItems[] = [
                    'raw_material_id' => $rawMaterialId,
                    'quantity' => $requiredQty,
                ];
            }

            if ($scrapQty > 0) {
                $scrapItems[] = [
                    'raw_material_id' => $rawMaterialId,
                    'quantity' => $scrapQty,
                ];
            }
        }

        if (!empty($productionReceiptItems)) {
            $this->rawMaterialStockDeductionService->deductStock(
                $productionReceiptItems,
                $productId,
                $userId,
                $movementDate,
                $referenceToken,
                RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
                ['product_movement_id' => $productMovementId]
            );
        }

        if (!empty($scrapItems)) {
            $this->rawMaterialStockDeductionService->deductStock(
                $scrapItems,
                $productId,
                $userId,
                $movementDate,
                $referenceToken,
                RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value,
                ['product_movement_id' => $productMovementId]
            );
        }
    }

    /**
     * Keep API responses data-transparent for manufacturing consumption calculations.
     */
    public function extractMaterialsSummary(array $consumptionPlan): array
    {
        return collect($consumptionPlan)
            ->map(fn (array $item) => [
                'raw_material_id' => (int) ($item['raw_material_id'] ?? 0),
                'required_qty' => round((float) ($item['required_qty'] ?? 0), 4),
                'scrap_qty' => round((float) ($item['scrap_qty'] ?? 0), 4),
                'total_consumption' => round((float) ($item['total_consumption'] ?? 0), 4),
            ])
            ->values()
            ->all();
    }

    public function getProductBom(int $productId): array
    {
        return ProductRawMaterial::where('product_id', $productId)
            ->get(['raw_material_id', 'quantity_per_unit', 'scrap_percentage'])
            ->map(fn ($item) => [
                'raw_material_id' => (int) $item->raw_material_id,
                'quantity_per_unit' => round((float) $item->quantity_per_unit, 4),
                'scrap_percentage' => round((float) $item->scrap_percentage, 4),
            ])
            ->values()
            ->all();
    }

    public function replaceProductBom(int $productId, array $bomItems): void
    {
        $normalizedBom = $this->normalizeBomItems($bomItems);

        ProductRawMaterial::where('product_id', $productId)->delete();

        foreach ($normalizedBom as $item) {
            ProductRawMaterial::create([
                'product_id' => $productId,
                'raw_material_id' => $item['raw_material_id'],
                'quantity' => $item['quantity_per_unit'],
                'quantity_per_unit' => $item['quantity_per_unit'],
                'scrap_percentage' => $item['scrap_percentage'],
            ]);
        }
    }

    public function getReorderBom(ProductReorder $productReorder): array
    {
        return $productReorder->bomItems()
            ->get(['raw_material_id', 'quantity_per_unit', 'scrap_percentage'])
            ->map(fn ($item) => [
                'raw_material_id' => (int) $item->raw_material_id,
                'quantity_per_unit' => round((float) $item->quantity_per_unit, 4),
                'scrap_percentage' => round((float) $item->scrap_percentage, 4),
            ])
            ->values()
            ->all();
    }

    public function replaceReorderBom(ProductReorder $productReorder, array $bomItems): void
    {
        $normalizedBom = $this->normalizeBomItems($bomItems);

        $productReorder->bomItems()->delete();

        foreach ($normalizedBom as $item) {
            ReorderProductRawMaterial::create([
                'product_reorder_id' => $productReorder->id,
                'raw_material_id' => $item['raw_material_id'],
                'quantity' => $item['quantity_per_unit'],
                'quantity_per_unit' => $item['quantity_per_unit'],
                'scrap_percentage' => $item['scrap_percentage'],
            ]);
        }
    }

    public function isDifferentBom(array $incomingBom, array $expectedBom): bool
    {
        $normalizeForCompare = function (array $items): array {
            return collect($this->normalizeBomItems($items))
                ->map(fn (array $item) => [
                    'raw_material_id' => (int) $item['raw_material_id'],
                    'quantity_per_unit' => round((float) $item['quantity_per_unit'], 4),
                    'scrap_percentage' => round((float) $item['scrap_percentage'], 4),
                ])
                ->sortBy('raw_material_id')
                ->values()
                ->all();
        };

        return $normalizeForCompare($incomingBom) !== $normalizeForCompare($expectedBom);
    }

    public function deleteConsumptionMovementsByToken(string $referenceToken): array
    {
        return $this->rawMaterialStockDeductionService->deleteReorderConsumptionMovementsByToken($referenceToken);
    }

    /**
     * Delete legacy internal-manufacturing consumptions created without reorder token.
     */
    public function deleteLegacyConsumptionMovementsForProduct(int $productId): array
    {
        $legacyNote = "Consumed for product ID {$productId}";

        $legacyMovements = RMStockMovement::where('direction', StockDirectionEnum::OUT->value)
            ->whereIn('movement_type', [
                RawMaterialStockMovementTypeEnum::MANUFACTURING->value,
                RawMaterialStockMovementTypeEnum::SCRAP->value,
                RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
                RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value,
            ])
            ->where('note', 'like', '%' . $legacyNote . '%')
            ->where('note', 'not like', '%REORDER_MOVEMENT_ID:%')
            ->get(['id', 'raw_material_id']);

        if ($legacyMovements->isEmpty()) {
            return [];
        }

        $rawMaterialIds = $legacyMovements->pluck('raw_material_id')->unique()->values()->all();

        RMStockMovement::whereIn('id', $legacyMovements->pluck('id')->all())->delete();

        return $rawMaterialIds;
    }

    public function rebuildInUsedFlags(array $rawMaterialIds): void
    {
        $this->rawMaterialStockDeductionService->rebuildInUsedFlags($rawMaterialIds);
    }
}
