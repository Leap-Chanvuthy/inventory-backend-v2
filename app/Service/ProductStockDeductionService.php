<?php

namespace App\Service;

use App\Enums\ProductStockMovementTypeEnum;
use App\Models\Product;
use App\Models\ProductMovement;

class ProductStockDeductionService
{
    public function __construct(
        private readonly ProductStockAllocationService $productStockAllocationService
    ) {
    }

    public function getAvailableStock(int $productId): float
    {
        return $this->productStockAllocationService->getAvailableStock($productId);
    }

    public function validateSufficientStock(array $saleItems): array
    {
        return $this->productStockAllocationService->validateSufficientStock($saleItems);
    }

    public function deductStockForSaleOrder(
        array $saleItems,
        int $saleOrderId,
        int $userId,
        string $movementDate
    ): void {
        foreach ($saleItems as $index => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $requiredQty = (float) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $requiredQty <= 0) {
                continue;
            }

            $product = Product::query()->findOrFail($productId);

            $this->productStockAllocationService->allocateProductForSale(
                $product,
                $requiredQty,
                $userId,
                $movementDate,
                [
                    'movement_type' => ProductStockMovementTypeEnum::SALE_ORDER->value,
                    'sale_order_id' => $saleOrderId,
                    'note' => 'DEDUCTION_INDEX:' . $index,
                ]
            );
        }
    }

    public function deleteSaleOrderMovementsByToken(string $referenceToken): array
    {
        $saleOrderId = $this->extractSaleOrderIdFromToken($referenceToken);
        if ($saleOrderId > 0) {
            return $this->productStockAllocationService->rollbackSaleOrderAllocations($saleOrderId);
        }

        $movementIds = ProductMovement::query()
            ->where('movement_type', ProductStockMovementTypeEnum::SALE_ORDER->value)
            ->where('note', 'like', '%' . $referenceToken . '%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($movementIds->isEmpty()) {
            return [];
        }

        $productIds = ProductMovement::query()
            ->whereIn('id', $movementIds->all())
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        ProductMovement::query()->whereIn('id', $movementIds->all())->delete();

        $this->rebuildIsSoldFlags($productIds);

        return $productIds;
    }

    public function rebuildIsSoldFlags(array $productIds): void
    {
        $this->productStockAllocationService->rebuildInMovementSoldFlags($productIds);
    }

    public function buildSaleOrderToken(int $saleOrderId): string
    {
        return "SALE_ORDER_ID:{$saleOrderId}";
    }

    private function extractSaleOrderIdFromToken(string $referenceToken): int
    {
        if (preg_match('/SALE_ORDER_ID:(\d+)/', $referenceToken, $matches) === 1) {
            return (int) ($matches[1] ?? 0);
        }

        return 0;
    }
}
