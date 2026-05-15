<?php

namespace App\Service;

use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Models\RawMaterialMovementAllocation;
use App\Models\RMStockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Backward-compatible façade for raw material deduction workflows.
 * Internally delegates lot-level stock control to RawMaterialStockAllocationService.
 */
class RawMaterialStockDeductionService
{
    public function __construct(
        protected RawMaterialStockAllocationService $rawMaterialStockAllocationService
    ) {
    }

    public function validateSufficientStock(array $bomItems, ?string $asOfMovementDate = null): array
    {
        return $this->rawMaterialStockAllocationService->validateSufficientStock($bomItems);
    }

    public function deductStock(
        array $bomItems,
        int $productId,
        int $userId,
        string $movementDate,
        ?string $referenceToken = null,
        ?string $movementType = null,
        array $context = []
    ): void {
        $resolvedMovementType = $movementType ?: RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value;

        foreach ($bomItems as $item) {
            $rawMaterialId = (int) ($item['raw_material_id'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);

            if ($rawMaterialId <= 0 || $quantity <= 0) {
                continue;
            }

            $noteParts = [];
            if (!empty($context['note'])) {
                $noteParts[] = (string) $context['note'];
            } else {
                $noteParts[] = "Consumed for product ID {$productId}";
            }

            if (!empty($referenceToken)) {
                $noteParts[] = (string) $referenceToken;
            }

            $this->rawMaterialStockAllocationService->allocateForConsumption(
                $rawMaterialId,
                $quantity,
                $userId,
                $movementDate,
                $resolvedMovementType,
                [
                    'product_id' => $productId,
                    'product_movement_id' => $context['product_movement_id'] ?? null,
                    'note' => implode(' | ', array_filter($noteParts)),
                ]
            );
        }
    }

    /**
     * Delete reorder-created OUT movements by token and return affected raw material IDs.
     * Also deletes related allocation rows for cleanup consistency.
     */
    public function deleteReorderConsumptionMovementsByToken(string $referenceToken, ?array $movementTypes = null): array
    {
        $movementTypes = $movementTypes ?? [
            RawMaterialStockMovementTypeEnum::MANUFACTURING->value,
            RawMaterialStockMovementTypeEnum::SCRAP->value,
            RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
            RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value,
        ];

        $movements = RMStockMovement::query()
            ->where('direction', 'OUT')
            ->whereIn('movement_type', $movementTypes)
            ->where('note', 'like', '%' . $referenceToken . '%')
            ->get(['id', 'raw_material_id']);

        if ($movements->isEmpty()) {
            return [];
        }

        $rawMaterialIds = $movements->pluck('raw_material_id')->unique()->values()->all();
        $movementIds = $movements->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($movementIds) {
            $allocations = RawMaterialMovementAllocation::query()
                ->whereIn('consumer_movement_id', $movementIds)
                ->lockForUpdate()
                ->get();

            $restoreBySource = [];
            foreach ($allocations as $allocation) {
                $sourceId = (int) $allocation->source_movement_id;
                $restoreBySource[$sourceId] = round(
                    (float) ($restoreBySource[$sourceId] ?? 0) + (float) $allocation->allocated_quantity,
                    4
                );
            }

            foreach ($restoreBySource as $sourceId => $restoreQty) {
                $sourceMovement = RMStockMovement::query()
                    ->whereKey($sourceId)
                    ->lockForUpdate()
                    ->first();

                if (!$sourceMovement) {
                    continue;
                }

                $restored = round((float) $sourceMovement->remaining_quantity + $restoreQty, 4);
                $sourceMovement->update([
                    'remaining_quantity' => min((float) $sourceMovement->quantity, $restored),
                ]);
            }

            RawMaterialMovementAllocation::query()
                ->whereIn('consumer_movement_id', $movementIds)
                ->delete();

            RMStockMovement::query()
                ->whereIn('id', $movementIds)
                ->delete();
        });

        return $rawMaterialIds;
    }

    public function rebuildInUsedFlags(array $rawMaterialIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $rawMaterialIds)));
        if (empty($ids)) {
            return;
        }

        foreach ($ids as $rawMaterialId) {
            $inMovements = RMStockMovement::query()
                ->where('raw_material_id', $rawMaterialId)
                ->where('direction', 'IN')
                ->get(['id', 'remaining_quantity']);

            foreach ($inMovements as $movement) {
                $movement->update([
                    'in_used' => (float) $movement->remaining_quantity < (float) $movement->quantity,
                ]);
            }
        }
    }

    public function getAvailableStock(int $rawMaterialId, ?string $asOfMovementDate = null): float
    {
        return $this->rawMaterialStockAllocationService->getAvailableStock($rawMaterialId, true);
    }
}
