<?php

namespace App\Service;

use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\SaleMethodEnum;
use App\Enums\StockDirectionEnum;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\ProductMovementAllocation;
use App\Models\SaleOrderItem;
use App\Service\Support\StockLotDateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductStockAllocationService
{
    private const FLOAT_EPSILON = 0.000001;

    public function __construct(
        protected StockLotDateService $stockLotDateService
    ) {
    }

    public function getAvailableStock(int $productId, bool $excludeExpired = true): float
    {
        $query = ProductMovement::query()
            ->where('product_id', $productId)
            ->where('direction', StockDirectionEnum::IN->value)
            ->where('remaining_quantity', '>', 0);

        if ($excludeExpired) {
            $query->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $this->stockLotDateService->today()->toDateString());
            });
        }

        return (float) $query->sum('remaining_quantity');
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

            $product = Product::query()->findOrFail($productId);
            $availableQty = $this->getAvailableStock($productId, true);

            if ($availableQty + self::FLOAT_EPSILON < $requiredQty) {
                $shortfalls[] = [
                    'product_id' => $productId,
                    'product_name' => (string) $product->product_name,
                    'product_sku_code' => (string) $product->product_sku_code,
                    'required_qty' => $requiredQty,
                    'available_qty' => $availableQty,
                    'shortfall_qty' => round($requiredQty - $availableQty, 4),
                ];
            }
        }

        return $shortfalls;
    }

    public function previewProductSaleAllocation(Product $product, float $quantity): array
    {
        $quantity = round($quantity, 4);
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        $methodValue = $this->resolveSaleMethod($product);
        $sourceMovements = $this->getAvailableSourceMovementsQuery($product, $methodValue)->get();
        $availableQty = round((float) $sourceMovements->sum('remaining_quantity'), 4);

        if ($sourceMovements->isEmpty() || $availableQty <= 0) {
            return [
                'product_id' => (int) $product->id,
                'product_name' => (string) $product->product_name,
                'sale_method' => $methodValue,
                'requested_quantity' => $quantity,
                'available_quantity' => max(0, $availableQty),
                'can_fulfill' => false,
                'estimated_total_usd' => 0,
                'estimated_total_riel' => 0,
                'estimated_average_unit_price_usd' => 0,
                'estimated_average_unit_price_riel' => 0,
                'lots' => [],
                'message' => 'No stock lots available yet. Create stock through purchase, manufacturing, reorder, or adjustment.',
            ];
        }

        if ($availableQty + self::FLOAT_EPSILON < $quantity) {
            return [
                'product_id' => (int) $product->id,
                'product_name' => (string) $product->product_name,
                'sale_method' => $methodValue,
                'requested_quantity' => $quantity,
                'available_quantity' => max(0, $availableQty),
                'can_fulfill' => false,
                'estimated_total_usd' => 0,
                'estimated_total_riel' => 0,
                'estimated_average_unit_price_usd' => 0,
                'estimated_average_unit_price_riel' => 0,
                'lots' => [],
                'message' => "Insufficient stock. Requested {$quantity}, but only {$availableQty} are available.",
            ];
        }

        $plan = $this->buildAllocationPlan($sourceMovements, $quantity);

        $lots = [];
        $totalUsd = 0.0;
        $totalRiel = 0.0;

        foreach ($plan as $entry) {
            /** @var ProductMovement $source */
            $source = $entry['source_movement'];
            $allocatedQty = (float) $entry['allocated_quantity'];

            $lineUsd = round($allocatedQty * (float) ($source->selling_unit_price_in_usd ?? 0), 4);
            $lineRiel = round($allocatedQty * (float) ($source->selling_unit_price_in_riel ?? 0), 4);

            $totalUsd += $lineUsd;
            $totalRiel += $lineRiel;

            $remainingBefore = (float) $source->remaining_quantity;
            $remainingAfter = max(0, round($remainingBefore - $allocatedQty, 4));

            $lots[] = [
                'source_movement_id' => (int) $source->id,
                'movement_type' => $this->enumValue($source->movement_type),
                'movement_date' => optional($source->movement_date)->toDateTimeString(),
                'allocated_quantity' => $allocatedQty,
                'remaining_quantity_before_sale' => $remainingBefore,
                'remaining_quantity_after_sale' => $remainingAfter,
                'selling_unit_price_in_usd' => (float) ($source->selling_unit_price_in_usd ?? 0),
                'selling_unit_price_in_riel' => (float) ($source->selling_unit_price_in_riel ?? 0),
                'selling_exchange_rate_from_usd_to_riel' => (float) ($source->selling_exchange_rate_from_usd_to_riel ?? 0),
                'selling_exchange_rate_from_riel_to_usd' => (float) ($source->selling_exchange_rate_from_riel_to_usd ?? 0),
                'line_total_usd' => $lineUsd,
                'line_total_riel' => $lineRiel,
            ];
        }

        return [
            'product_id' => (int) $product->id,
            'product_name' => (string) $product->product_name,
            'sale_method' => $methodValue,
            'requested_quantity' => $quantity,
            'available_quantity' => $availableQty,
            'can_fulfill' => true,
            'estimated_total_usd' => round($totalUsd, 4),
            'estimated_total_riel' => round($totalRiel, 4),
            'estimated_average_unit_price_usd' => $quantity > 0 ? round($totalUsd / $quantity, 4) : 0,
            'estimated_average_unit_price_riel' => $quantity > 0 ? round($totalRiel / $quantity, 4) : 0,
            'lots' => $lots,
            'message' => null,
        ];
    }

    public function allocateProductForSale(
        Product $product,
        float $quantity,
        int $userId,
        string|null $movementDate = null,
        array $saleContext = []
    ): array {
        $quantity = round($quantity, 4);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($product, $quantity, $userId, $movementDate, $saleContext) {
            $methodValue = $this->resolveSaleMethod($product);

            $sourceMovements = $this->getAvailableSourceMovementsQuery($product, $methodValue)
                ->lockForUpdate()
                ->get();

            $availableQty = (float) $sourceMovements->sum('remaining_quantity');
            if ($availableQty + self::FLOAT_EPSILON < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        "Insufficient stock for {$product->product_name}. Requested {$quantity}, but only {$availableQty} are available.",
                    ],
                ]);
            }

            $plan = $this->buildAllocationPlan($sourceMovements, $quantity);

            $summary = $this->summarizePlan($plan, $quantity);
            $movementDateValue = $movementDate ?: now()->toDateTimeString();

            $noteParts = ['SALE_ORDER'];
            if (!empty($saleContext['sale_order_id'])) {
                $noteParts[] = 'SALE_ORDER_ID:' . (int) $saleContext['sale_order_id'];
            }
            if (!empty($saleContext['sale_order_item_id'])) {
                $noteParts[] = 'SALE_ORDER_ITEM_ID:' . (int) $saleContext['sale_order_item_id'];
            }
            if (!empty($saleContext['note'])) {
                $noteParts[] = (string) $saleContext['note'];
            }

            $saleMovement = ProductMovement::query()->create([
                'product_id' => (int) $product->id,
                'quantity' => $quantity,
                'remaining_quantity' => 0,
                'source_movement_id' => null,
                'product_status' => $saleContext['product_status'] ?? null,
                'direction' => StockDirectionEnum::OUT->value,
                'movement_type' => $saleContext['movement_type'] ?? ProductStockMovementTypeEnum::SALE_ORDER->value,
                'is_sold' => true,
                'movement_date' => $movementDateValue,
                'expiry_date' => null,
                'purchase_unit_price_in_usd' => $summary['average_cost_unit_price_in_usd'],
                'purchase_total_price_in_usd' => $summary['total_cost_usd'],
                'purchase_unit_price_in_riel' => $summary['average_cost_unit_price_in_riel'],
                'purchase_total_price_in_riel' => $summary['total_cost_riel'],
                'exchange_rate_from_usd_to_riel' => $summary['average_cost_rate_usd_to_riel'],
                'exchange_rate_from_riel_to_usd' => $summary['average_cost_rate_riel_to_usd'],
                'selling_unit_price_in_usd' => $summary['average_selling_unit_price_in_usd'],
                'selling_unit_price_in_riel' => $summary['average_selling_unit_price_in_riel'],
                'selling_exchange_rate_from_usd_to_riel' => $summary['average_selling_rate_usd_to_riel'],
                'selling_exchange_rate_from_riel_to_usd' => $summary['average_selling_rate_riel_to_usd'],
                'created_by' => $userId,
                'last_updated_by' => $userId,
                'note' => implode(' | ', $noteParts),
            ]);

            foreach ($plan as $entry) {
                /** @var ProductMovement $source */
                $source = $entry['source_movement'];
                $allocatedQty = (float) $entry['allocated_quantity'];

                ProductMovementAllocation::query()->create([
                    'sale_movement_id' => (int) $saleMovement->id,
                    'source_movement_id' => (int) $source->id,
                    'allocated_quantity' => $allocatedQty,
                    'selling_unit_price_in_usd' => (float) ($source->selling_unit_price_in_usd ?? 0),
                    'selling_unit_price_in_riel' => (float) ($source->selling_unit_price_in_riel ?? 0),
                    'cost_unit_price_in_usd' => (float) ($source->purchase_unit_price_in_usd ?? 0),
                    'cost_unit_price_in_riel' => (float) ($source->purchase_unit_price_in_riel ?? 0),
                    'selling_exchange_rate_from_usd_to_riel' => (float) ($source->selling_exchange_rate_from_usd_to_riel ?? 0),
                    'selling_exchange_rate_from_riel_to_usd' => (float) ($source->selling_exchange_rate_from_riel_to_usd ?? 0),
                    'cost_exchange_rate_from_usd_to_riel' => (float) ($source->exchange_rate_from_usd_to_riel ?? 0),
                    'cost_exchange_rate_from_riel_to_usd' => (float) ($source->exchange_rate_from_riel_to_usd ?? 0),
                    'allocated_at' => now(),
                    'created_by' => $userId,
                ]);

                $nextRemaining = max(0, round((float) $source->remaining_quantity - $allocatedQty, 4));
                $source->update([
                    'remaining_quantity' => $nextRemaining,
                    'is_sold' => true,
                ]);
            }

            $saleMovement->load(['saleAllocations.sourceMovement']);

            return [
                'sale_movement' => $saleMovement,
                'allocations' => $saleMovement->saleAllocations,
                'allocation_summary' => [
                    'sale_method' => $methodValue,
                    'total_quantity' => $quantity,
                    'total_amount_usd' => $summary['total_selling_usd'],
                    'total_amount_riel' => $summary['total_selling_riel'],
                    'average_unit_price_usd' => $summary['average_selling_unit_price_in_usd'],
                    'average_unit_price_riel' => $summary['average_selling_unit_price_in_riel'],
                    'lots' => $saleMovement->saleAllocations->map(function (ProductMovementAllocation $allocation) {
                        $source = $allocation->sourceMovement;
                        $lineTotalUsd = round((float) $allocation->allocated_quantity * (float) ($allocation->selling_unit_price_in_usd ?? 0), 4);
                        $lineTotalRiel = round((float) $allocation->allocated_quantity * (float) ($allocation->selling_unit_price_in_riel ?? 0), 4);

                        return [
                            'source_movement_id' => (int) $allocation->source_movement_id,
                            'movement_type' => $source ? $this->enumValue($source->movement_type) : null,
                            'movement_date' => $source && $source->movement_date ? $source->movement_date->toDateTimeString() : null,
                            'allocated_quantity' => (float) $allocation->allocated_quantity,
                            'selling_unit_price_in_usd' => (float) ($allocation->selling_unit_price_in_usd ?? 0),
                            'selling_unit_price_in_riel' => (float) ($allocation->selling_unit_price_in_riel ?? 0),
                            'line_total_usd' => $lineTotalUsd,
                            'line_total_riel' => $lineTotalRiel,
                        ];
                    })->values()->all(),
                ],
            ];
        });
    }

    public function rollbackSaleOrderAllocations(int $saleOrderId): array
    {
        $saleMovementIds = SaleOrderItem::query()
            ->where('sale_order_id', $saleOrderId)
            ->whereNotNull('sale_movement_id')
            ->pluck('sale_movement_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($saleMovementIds->isEmpty()) {
            return [];
        }

        return DB::transaction(function () use ($saleMovementIds) {
            $productIds = [];

            $saleMovements = ProductMovement::query()
                ->whereIn('id', $saleMovementIds->all())
                ->lockForUpdate()
                ->get();

            foreach ($saleMovements as $saleMovement) {
                $productIds[] = (int) $saleMovement->product_id;

                $allocations = ProductMovementAllocation::query()
                    ->where('sale_movement_id', $saleMovement->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($allocations as $allocation) {
                    $source = ProductMovement::query()
                        ->where('id', (int) $allocation->source_movement_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$source) {
                        continue;
                    }

                    $restored = round((float) $source->remaining_quantity + (float) $allocation->allocated_quantity, 4);
                    $maxQty = (float) $source->quantity;

                    $source->update([
                        'remaining_quantity' => min($maxQty, $restored),
                    ]);
                }

                ProductMovementAllocation::query()
                    ->where('sale_movement_id', $saleMovement->id)
                    ->delete();

                $saleMovement->delete();
            }

            SaleOrderItem::query()
                ->whereIn('sale_movement_id', $saleMovementIds->all())
                ->update(['sale_movement_id' => null]);

            $this->rebuildInMovementSoldFlags($productIds);

            return array_values(array_unique(array_map('intval', $productIds)));
        });
    }

    public function rebuildInMovementSoldFlags(array $productIds): void
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (empty($normalizedIds)) {
            return;
        }

        ProductMovement::query()
            ->whereIn('product_id', $normalizedIds)
            ->where('direction', StockDirectionEnum::IN->value)
            ->each(function (ProductMovement $movement) {
                $hasAllocations = ProductMovementAllocation::query()
                    ->where('source_movement_id', $movement->id)
                    ->exists();

                $movement->update([
                    'is_sold' => $hasAllocations,
                ]);
            });
    }

    private function getAvailableSourceMovementsQuery(Product $product, string $saleMethod)
    {
        $direction = strtoupper($saleMethod) === SaleMethodEnum::LIFO->value ? 'desc' : 'asc';

        return ProductMovement::query()
            ->where('product_id', $product->id)
            ->where('direction', StockDirectionEnum::IN->value)
            ->where('remaining_quantity', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $this->stockLotDateService->today()->toDateString());
            })
            ->orderBy('movement_date', $direction)
            ->orderBy('id', $direction);
    }

    private function buildAllocationPlan(Collection $sourceMovements, float $requiredQty): array
    {
        $remainingQty = round($requiredQty, 4);
        $plan = [];

        foreach ($sourceMovements as $source) {
            if ($remainingQty <= self::FLOAT_EPSILON) {
                break;
            }

            $available = (float) $source->remaining_quantity;
            if ($available <= self::FLOAT_EPSILON) {
                continue;
            }

            $allocated = min($available, $remainingQty);
            $allocated = round($allocated, 4);

            if ($allocated <= self::FLOAT_EPSILON) {
                continue;
            }

            $plan[] = [
                'source_movement' => $source,
                'allocated_quantity' => $allocated,
            ];

            $remainingQty = round($remainingQty - $allocated, 4);
        }

        return $plan;
    }

    private function summarizePlan(array $plan, float $quantity): array
    {
        $totalSellingUsd = 0.0;
        $totalSellingRiel = 0.0;
        $totalCostUsd = 0.0;
        $totalCostRiel = 0.0;

        $weightedSellingUsdToRiel = 0.0;
        $weightedSellingRielToUsd = 0.0;
        $weightedCostUsdToRiel = 0.0;
        $weightedCostRielToUsd = 0.0;

        foreach ($plan as $entry) {
            /** @var ProductMovement $source */
            $source = $entry['source_movement'];
            $allocatedQty = (float) $entry['allocated_quantity'];

            $sellingUsd = (float) ($source->selling_unit_price_in_usd ?? 0);
            $sellingRiel = (float) ($source->selling_unit_price_in_riel ?? 0);
            $costUsd = (float) ($source->purchase_unit_price_in_usd ?? 0);
            $costRiel = (float) ($source->purchase_unit_price_in_riel ?? 0);

            $totalSellingUsd += $allocatedQty * $sellingUsd;
            $totalSellingRiel += $allocatedQty * $sellingRiel;
            $totalCostUsd += $allocatedQty * $costUsd;
            $totalCostRiel += $allocatedQty * $costRiel;

            $weightedSellingUsdToRiel += $allocatedQty * (float) ($source->selling_exchange_rate_from_usd_to_riel ?? 0);
            $weightedSellingRielToUsd += $allocatedQty * (float) ($source->selling_exchange_rate_from_riel_to_usd ?? 0);
            $weightedCostUsdToRiel += $allocatedQty * (float) ($source->exchange_rate_from_usd_to_riel ?? 0);
            $weightedCostRielToUsd += $allocatedQty * (float) ($source->exchange_rate_from_riel_to_usd ?? 0);
        }

        $totalSellingUsd = round($totalSellingUsd, 4);
        $totalSellingRiel = round($totalSellingRiel, 4);
        $totalCostUsd = round($totalCostUsd, 4);
        $totalCostRiel = round($totalCostRiel, 4);

        return [
            'total_selling_usd' => $totalSellingUsd,
            'total_selling_riel' => $totalSellingRiel,
            'total_cost_usd' => $totalCostUsd,
            'total_cost_riel' => $totalCostRiel,
            'average_selling_unit_price_in_usd' => $quantity > 0 ? round($totalSellingUsd / $quantity, 4) : 0,
            'average_selling_unit_price_in_riel' => $quantity > 0 ? round($totalSellingRiel / $quantity, 4) : 0,
            'average_cost_unit_price_in_usd' => $quantity > 0 ? round($totalCostUsd / $quantity, 4) : 0,
            'average_cost_unit_price_in_riel' => $quantity > 0 ? round($totalCostRiel / $quantity, 4) : 0,
            'average_selling_rate_usd_to_riel' => $quantity > 0 ? round($weightedSellingUsdToRiel / $quantity, 4) : 0,
            'average_selling_rate_riel_to_usd' => $quantity > 0 ? round($weightedSellingRielToUsd / $quantity, 8) : 0,
            'average_cost_rate_usd_to_riel' => $quantity > 0 ? round($weightedCostUsdToRiel / $quantity, 4) : 0,
            'average_cost_rate_riel_to_usd' => $quantity > 0 ? round($weightedCostRielToUsd / $quantity, 8) : 0,
        ];
    }

    private function resolveSaleMethod(Product $product): string
    {
        $method = $this->enumValue($product->sale_method);
        return strtoupper((string) $method) === SaleMethodEnum::LIFO->value
            ? SaleMethodEnum::LIFO->value
            : SaleMethodEnum::FIFO->value;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
