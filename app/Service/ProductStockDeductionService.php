<?php

namespace App\Service;

use App\Enums\ProductStatusEnum;
use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Models\Product;
use App\Models\ProductMovement;
use Illuminate\Support\Collection;

class ProductStockDeductionService
{
    private const FLOAT_EPSILON = 0.000001;

    public function getAvailableStock(int $productId): float
    {
        $totalIn = ProductMovement::where('product_id', $productId)
            ->where('direction', StockDirectionEnum::IN->value)
            ->sum('quantity');

        $totalOut = ProductMovement::where('product_id', $productId)
            ->where('direction', StockDirectionEnum::OUT->value)
            ->sum('quantity');

        return max(0, (float) $totalIn - (float) $totalOut);
    }

    public function validateSufficientStock(array $saleItems): array
    {
        $shortfalls = [];

        foreach ($saleItems as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $requiredQty = (float) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $requiredQty <= 0) {
                continue;
            }

            $product = Product::findOrFail($productId);
            $availableQty = $this->getAvailableStock($productId);

            if ($availableQty < $requiredQty) {
                $shortfalls[] = [
                    'product_id' => $productId,
                    'product_name' => $product->product_name,
                    'product_sku_code' => $product->product_sku_code,
                    'required_qty' => $requiredQty,
                    'available_qty' => $availableQty,
                    'shortfall_qty' => round($requiredQty - $availableQty, 4),
                ];
            }
        }

        return $shortfalls;
    }

    public function deductStockForSaleOrder(
        array $saleItems,
        int $saleOrderId,
        int $userId,
        string $movementDate
    ): void {
        $referenceToken = $this->buildSaleOrderToken($saleOrderId);

        foreach ($saleItems as $item) {
            $productId = (int) $item['product_id'];
            $requiredQty = (float) $item['quantity'];

            $product = Product::findOrFail($productId);
            $batches = $this->getConsumableBatches($product);
            $availableQty = (float) $batches->sum('available_qty');

            if ($availableQty < $requiredQty) {
                throw new \RuntimeException("Insufficient stock for product ID {$productId}");
            }

            $remaining = $requiredQty;

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                /** @var ProductMovement $inMovement */
                $inMovement = $batch['movement'];
                $batchAvailable = (float) $batch['available_qty'];
                $consume = min($remaining, $batchAvailable);

                ProductMovement::create([
                    'product_id' => $productId,
                    'quantity' => $consume,
                    'product_status' => ProductStatusEnum::COMPLETED->value,
                    'is_sold' => false,
                    'direction' => StockDirectionEnum::OUT->value,
                    'movement_type' => ProductStockMovementTypeEnum::SALE_ORDER->value,
                    'movement_date' => $movementDate,
                    'purchase_unit_price_in_usd' => (float) ($inMovement->purchase_unit_price_in_usd ?? 0),
                    'purchase_total_price_in_usd' => round((float) ($inMovement->purchase_unit_price_in_usd ?? 0) * $consume, 2),
                    'purchase_unit_price_in_riel' => (float) ($inMovement->purchase_unit_price_in_riel ?? 0),
                    'purchase_total_price_in_riel' => round((float) ($inMovement->purchase_unit_price_in_riel ?? 0) * $consume, 2),
                    'exchange_rate_from_usd_to_riel' => (float) ($inMovement->exchange_rate_from_usd_to_riel ?? 0),
                    'exchange_rate_from_riel_to_usd' => (float) ($inMovement->exchange_rate_from_riel_to_usd ?? 0),
                    'selling_unit_price_in_usd' => (float) ($item['unit_price_in_usd'] ?? 0),
                    'selling_unit_price_in_riel' => (float) ($item['unit_price_in_riel'] ?? 0),
                    'selling_exchange_rate_from_usd_to_riel' => (float) ($item['exchange_rate_from_usd_to_riel'] ?? 0),
                    'selling_exchange_rate_from_riel_to_usd' => (float) ($item['exchange_rate_from_riel_to_usd'] ?? 0),
                    'created_by' => $userId,
                    'last_updated_by' => $userId,
                    'note' => "Sale Order deduction | {$referenceToken}",
                ]);

                // Mark source IN movement as sold as soon as any quantity is consumed from it.
                if ($consume > 0) {
                    $inMovement->update(['is_sold' => true]);
                }

                $remaining -= $consume;
            }
        }
    }

    public function deleteSaleOrderMovementsByToken(string $referenceToken): array
    {
        $movements = ProductMovement::where('direction', StockDirectionEnum::OUT->value)
            ->where('movement_type', ProductStockMovementTypeEnum::SALE_ORDER->value)
            ->where('note', 'like', '%' . $referenceToken . '%')
            ->get(['id', 'product_id']);

        if ($movements->isEmpty()) {
            return [];
        }

        $productIds = $movements->pluck('product_id')->unique()->values()->all();
        ProductMovement::whereIn('id', $movements->pluck('id')->all())->delete();

        return $productIds;
    }

    public function rebuildIsSoldFlags(array $productIds): void
    {
        foreach (array_unique(array_map('intval', $productIds)) as $productId) {
            if ($productId <= 0) {
                continue;
            }

            ProductMovement::where('product_id', $productId)
                ->where('direction', StockDirectionEnum::IN->value)
                ->update(['is_sold' => false]);

            $remainingOut = (float) ProductMovement::where('product_id', $productId)
                ->where('direction', StockDirectionEnum::OUT->value)
                ->sum('quantity');

            if ($remainingOut <= 0) {
                continue;
            }

            $product = Product::find($productId);
            if (!$product) {
                continue;
            }

            $methodValue = is_object($product->sale_method) ? $product->sale_method->value : (string) $product->sale_method;
            $order = strtoupper($methodValue) === 'LIFO' ? 'desc' : 'asc';

            $inRows = ProductMovement::where('product_id', $productId)
                ->where('direction', StockDirectionEnum::IN->value)
                ->orderBy('movement_date', $order)
                ->orderBy('id', $order)
                ->get(['id', 'quantity']);

            foreach ($inRows as $inRow) {
                if ($remainingOut <= 0) {
                    break;
                }

                $inQty = (float) $inRow->quantity;
                if ($remainingOut + self::FLOAT_EPSILON >= $inQty) {
                    ProductMovement::where('id', $inRow->id)->update(['is_sold' => true]);
                    $remainingOut -= $inQty;
                    continue;
                }

                // Partial historical consumption still means this IN movement has been sold.
                if ($remainingOut > self::FLOAT_EPSILON) {
                    ProductMovement::where('id', $inRow->id)->update(['is_sold' => true]);
                }

                break;
            }
        }
    }

    public function buildSaleOrderToken(int $saleOrderId): string
    {
        return "SALE_ORDER_ID:{$saleOrderId}";
    }

    /**
     * Return IN movements with calculated remaining quantity after historical OUT consumption.
     */
    private function getConsumableBatches(Product $product): Collection
    {
        $methodValue = is_object($product->sale_method) ? $product->sale_method->value : (string) $product->sale_method;
        $order = strtoupper($methodValue) === 'LIFO' ? 'desc' : 'asc';

        $inMovements = ProductMovement::where('product_id', $product->id)
            ->where('direction', StockDirectionEnum::IN->value)
            ->orderBy('movement_date', $order)
            ->orderBy('id', $order)
            ->get();

        $remainingOut = (float) ProductMovement::where('product_id', $product->id)
            ->where('direction', StockDirectionEnum::OUT->value)
            ->sum('quantity');

        return $inMovements->map(function (ProductMovement $inMovement) use (&$remainingOut) {
            $inQty = (float) $inMovement->quantity;
            $consumed = min($remainingOut, $inQty);
            $available = max(0, $inQty - $consumed);
            $remainingOut = max(0, $remainingOut - $consumed);

            return [
                'movement' => $inMovement,
                'available_qty' => $available,
            ];
        })->filter(fn ($row) => (float) $row['available_qty'] > 0)->values();
    }
}
