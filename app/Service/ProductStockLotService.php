<?php

namespace App\Service;

use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\ProductMovementAllocation;
use App\Service\Support\StockLotDateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductStockLotService
{
    public function __construct(
        protected StockLotDateService $stockLotDateService
    ) {
    }

    public function getHierarchy(Product $product, bool $includeChildren = true): array
    {
        $parentLots = ProductMovement::query()
            ->where('product_id', (int) $product->id)
            ->where('direction', StockDirectionEnum::IN->value)
            ->orderBy('movement_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($parentLots->isEmpty()) {
            return [];
        }

        $parentIds = $parentLots->pluck('id')->map(fn ($id) => (int) $id)->all();

        $allocationsBySource = ProductMovementAllocation::query()
            ->with([
                'saleMovement:id,movement_type,movement_date,selling_unit_price_in_usd,selling_unit_price_in_riel',
                'saleMovement.saleOrderItem:id,sale_order_id,sale_movement_id,quantity,total_price_in_usd,total_price_in_riel',
                'saleMovement.saleOrderItem.saleOrder:id,order_no,customer_id,order_date',
                'saleMovement.saleOrderItem.saleOrder.customer:id,fullname',
            ])
            ->whereIn('source_movement_id', $parentIds)
            ->get()
            ->groupBy('source_movement_id');

        $childMovementsBySource = ProductMovement::query()
            ->whereIn('source_movement_id', $parentIds)
            ->where('direction', StockDirectionEnum::OUT->value)
            ->orderBy('movement_date', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy('source_movement_id');

        $rows = [];

        foreach ($parentLots as $lot) {
            $sourceId = (int) $lot->id;
            $quantity = round((float) $lot->quantity, 4);
            $remaining = max(0, round((float) $lot->remaining_quantity, 4));
            $expiryDate = $lot->expiry_date?->toDateString();
            $isExpired = $this->stockLotDateService->isExpired($expiryDate);

            /** @var Collection<int, ProductMovementAllocation> $saleAllocations */
            $saleAllocations = $allocationsBySource->get($sourceId, collect());
            /** @var Collection<int, ProductMovement> $childMovements */
            $childMovements = $childMovementsBySource->get($sourceId, collect());

            $soldQuantity = round((float) $saleAllocations->sum('allocated_quantity'), 4);
            $scrappedQuantity = round((float) $childMovements
                ->filter(fn (ProductMovement $row) => $this->movementType($row) === ProductStockMovementTypeEnum::SCRAP->value)
                ->sum('quantity'), 4);
            $adjustedOutQuantity = round((float) $childMovements
                ->filter(fn (ProductMovement $row) => $this->movementType($row) === ProductStockMovementTypeEnum::ADJUSTMENT_OUT->value)
                ->sum('quantity'), 4);

            $status = $this->resolveLotStatus($quantity, $remaining, $isExpired);
            $canUse = !$isExpired && $remaining > 0;

            $children = [];
            if ($includeChildren) {
                $children = array_merge(
                    $this->mapSaleAllocationChildren($saleAllocations),
                    $this->mapMovementChildren($childMovements)
                );

                usort($children, function (array $a, array $b) {
                    $ad = $a['date'] ?? '';
                    $bd = $b['date'] ?? '';
                    return strcmp((string) $ad, (string) $bd);
                });
            }

            $rows[] = [
                'id' => $sourceId,
                'batch_code' => $this->batchCode($sourceId),
                'movement_type' => $this->movementType($lot),
                'direction' => StockDirectionEnum::IN->value,
                'movement_date' => optional($lot->movement_date)->toDateTimeString(),
                'expiry_date' => $expiryDate,
                'is_expired' => $isExpired,
                'days_until_expiry' => $this->stockLotDateService->daysUntilExpiry($expiryDate),
                'quantity' => $quantity,
                'remaining_quantity' => $remaining,
                'sold_quantity' => $soldQuantity,
                'scrapped_quantity' => $scrappedQuantity,
                'adjusted_out_quantity' => $adjustedOutQuantity,
                'available_quantity' => $remaining,
                'selling_unit_price_in_usd' => (float) ($lot->selling_unit_price_in_usd ?? 0),
                'selling_unit_price_in_riel' => (float) ($lot->selling_unit_price_in_riel ?? 0),
                'status' => $status,
                'can_sale' => $canUse,
                'can_scrap' => $canUse,
                'disabled_reason' => $canUse ? null : $this->resolveDisabledReason($isExpired, $remaining),
                'children' => $children,
            ];
        }

        return $rows;
    }

    public function getStockLotSummary(Product $product): array
    {
        $lots = $this->getHierarchy($product, false);

        $availableQty = 0.0;
        $expiredQty = 0.0;
        $totalOriginal = 0.0;
        $totalSold = 0.0;
        $totalScrapped = 0.0;
        $totalRemaining = 0.0;

        foreach ($lots as $lot) {
            $remaining = (float) ($lot['remaining_quantity'] ?? 0);
            $totalOriginal += (float) ($lot['quantity'] ?? 0);
            $totalSold += (float) ($lot['sold_quantity'] ?? 0);
            $totalScrapped += (float) ($lot['scrapped_quantity'] ?? 0);
            $totalRemaining += $remaining;

            if ((bool) ($lot['is_expired'] ?? false)) {
                $expiredQty += $remaining;
            } else {
                $availableQty += $remaining;
            }
        }

        $saleMethod = $product->sale_method instanceof \BackedEnum
            ? $product->sale_method->value
            : (string) ($product->sale_method ?? 'FIFO');

        return [
            'available_quantity' => round($availableQty, 4),
            'expired_quantity' => round($expiredQty, 4),
            'total_original_quantity' => round($totalOriginal, 4),
            'total_sold_quantity' => round($totalSold, 4),
            'total_scrapped_quantity' => round($totalScrapped, 4),
            'total_remaining_quantity' => round($totalRemaining, 4),
            'sale_method' => strtoupper($saleMethod),
        ];
    }

    public function getScrapEligibleLots(Product $product, bool $includeDisabled = false): array
    {
        $lots = $this->getHierarchy($product, false);

        if ($includeDisabled) {
            return $lots;
        }

        return array_values(array_filter($lots, function (array $lot) {
            return (bool) ($lot['can_scrap'] ?? false);
        }));
    }

    public function createScrap(Product $product, array $payload, int $userId): array
    {
        $quantity = round((float) ($payload['quantity'] ?? 0), 4);
        $sourceMovementId = (int) ($payload['source_movement_id'] ?? 0);
        $movementDate = $this->stockLotDateService->normalizeMovementDate($payload['movement_date'] ?? null);
        $reason = trim((string) ($payload['reason'] ?? ''));
        $note = trim((string) ($payload['note'] ?? ''));

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        if ($sourceMovementId <= 0) {
            throw ValidationException::withMessages([
                'source_movement_id' => ['Please select a stock batch to scrap from.'],
            ]);
        }

        return DB::transaction(function () use ($product, $quantity, $sourceMovementId, $movementDate, $reason, $note, $userId) {
            /** @var ProductMovement|null $sourceLot */
            $sourceLot = ProductMovement::query()
                ->where('id', $sourceMovementId)
                ->where('product_id', (int) $product->id)
                ->where('direction', StockDirectionEnum::IN->value)
                ->lockForUpdate()
                ->first();

            if (!$sourceLot) {
                throw ValidationException::withMessages([
                    'source_movement_id' => ['Selected stock batch was not found for this product.'],
                ]);
            }

            $this->assertLotCanBeConsumed($sourceLot, $quantity);

            $scrapNoteParts = ['SCRAP'];
            if ($reason !== '') {
                $scrapNoteParts[] = "REASON:{$reason}";
            }
            if ($note !== '') {
                $scrapNoteParts[] = $note;
            }

            $scrapMovement = ProductMovement::query()->create([
                'product_id' => (int) $product->id,
                'quantity' => $quantity,
                'remaining_quantity' => 0,
                'product_status' => $payload['product_status'] ?? null,
                'direction' => StockDirectionEnum::OUT->value,
                'movement_type' => ProductStockMovementTypeEnum::SCRAP->value,
                'source_movement_id' => (int) $sourceLot->id,
                'expiry_date' => $sourceLot->expiry_date,
                'is_sold' => true,
                'movement_date' => $movementDate,
                'purchase_unit_price_in_usd' => (float) ($sourceLot->purchase_unit_price_in_usd ?? 0),
                'purchase_total_price_in_usd' => round((float) ($sourceLot->purchase_unit_price_in_usd ?? 0) * $quantity, 4),
                'purchase_unit_price_in_riel' => (float) ($sourceLot->purchase_unit_price_in_riel ?? 0),
                'purchase_total_price_in_riel' => round((float) ($sourceLot->purchase_unit_price_in_riel ?? 0) * $quantity, 4),
                'exchange_rate_from_usd_to_riel' => (float) ($sourceLot->exchange_rate_from_usd_to_riel ?? 0),
                'exchange_rate_from_riel_to_usd' => (float) ($sourceLot->exchange_rate_from_riel_to_usd ?? 0),
                'selling_unit_price_in_usd' => (float) ($sourceLot->selling_unit_price_in_usd ?? 0),
                'selling_unit_price_in_riel' => (float) ($sourceLot->selling_unit_price_in_riel ?? 0),
                'selling_exchange_rate_from_usd_to_riel' => (float) ($sourceLot->selling_exchange_rate_from_usd_to_riel ?? 0),
                'selling_exchange_rate_from_riel_to_usd' => (float) ($sourceLot->selling_exchange_rate_from_riel_to_usd ?? 0),
                'created_by' => $userId,
                'last_updated_by' => $userId,
                'note' => implode(' | ', $scrapNoteParts),
            ]);

            $sourceLot->update([
                'remaining_quantity' => max(0, round((float) $sourceLot->remaining_quantity - $quantity, 4)),
                'is_sold' => true,
                'last_updated_by' => $userId,
            ]);

            return [
                'scrap_movement' => $scrapMovement->fresh(),
                'source_lot' => $sourceLot->fresh(),
                'stock_lot_summary' => $this->getStockLotSummary($product),
            ];
        });
    }

    public function assertLotCanBeConsumed(ProductMovement $lot, float $quantity): void
    {
        if ($this->movementType($lot) === ProductStockMovementTypeEnum::SCRAP->value) {
            throw ValidationException::withMessages([
                'source_movement_id' => ['Scrap movement cannot be used as a source stock batch.'],
            ]);
        }

        $remaining = (float) $lot->remaining_quantity;
        if ($remaining <= 0) {
            throw ValidationException::withMessages([
                'source_movement_id' => ['This stock batch has no remaining stock.'],
            ]);
        }

        $expiryDate = $lot->expiry_date?->toDateString();
        if ($this->stockLotDateService->isExpired($expiryDate)) {
            throw ValidationException::withMessages([
                'source_movement_id' => ['This stock batch is expired and cannot be used.'],
            ]);
        }

        if ($quantity > $remaining) {
            throw ValidationException::withMessages([
                'quantity' => ['Requested quantity exceeds this stock batch remaining stock.'],
            ]);
        }
    }

    private function movementType(ProductMovement $movement): string
    {
        return $movement->movement_type instanceof \BackedEnum
            ? $movement->movement_type->value
            : (string) $movement->movement_type;
    }

    private function resolveLotStatus(float $quantity, float $remaining, bool $isExpired): string
    {
        if ($isExpired && $remaining > 0) {
            return 'EXPIRED';
        }

        if ($remaining <= 0) {
            return 'FULLY_USED';
        }

        if ($remaining < $quantity) {
            return 'PARTIALLY_USED';
        }

        return 'AVAILABLE';
    }

    private function resolveDisabledReason(bool $isExpired, float $remaining): string
    {
        if ($isExpired) {
            return 'Expired stock cannot be used.';
        }

        if ($remaining <= 0) {
            return 'No remaining stock in this batch.';
        }

        return 'Stock batch is not eligible.';
    }

    private function mapSaleAllocationChildren(Collection $allocations): array
    {
        $children = [];

        foreach ($allocations as $allocation) {
            $saleMovement = $allocation->saleMovement;
            $saleOrderItem = $saleMovement?->saleOrderItem;
            $saleOrder = $saleOrderItem?->saleOrder;
            $customer = $saleOrder?->customer;
            $allocatedQty = (float) ($allocation->allocated_quantity ?? 0);
            $unitPrice = (float) ($allocation->selling_unit_price_in_usd ?? 0);

            $children[] = [
                'id' => (int) $allocation->id,
                'type' => ProductStockMovementTypeEnum::SALE_ORDER->value,
                'reference' => $saleOrder?->order_no ? 'SO-' . $saleOrder->order_no : 'PM-' . (int) ($saleMovement?->id ?? 0),
                'quantity' => $allocatedQty,
                'date' => optional($saleMovement?->movement_date)->toDateTimeString(),
                'unit_price' => $unitPrice,
                'total' => round($allocatedQty * $unitPrice, 4),
                'customer_id' => $saleOrder?->customer_id ? (int) $saleOrder->customer_id : null,
                'customer_name' => $customer?->fullname,
                'sale_order_id' => $saleOrder?->id ? (int) $saleOrder->id : null,
                'sale_order_number' => $saleOrder?->order_no,
            ];
        }

        return $children;
    }

    private function mapMovementChildren(Collection $movements): array
    {
        return $movements->map(function (ProductMovement $movement) {
            $qty = (float) ($movement->quantity ?? 0);
            $unitPrice = (float) ($movement->selling_unit_price_in_usd ?? 0);

            return [
                'id' => (int) $movement->id,
                'type' => $this->movementType($movement),
                'reference' => $this->batchCode((int) $movement->id),
                'quantity' => $qty,
                'date' => optional($movement->movement_date)->toDateTimeString(),
                'unit_price' => $unitPrice,
                'total' => round($qty * $unitPrice, 4),
                'reason' => $movement->note,
            ];
        })->values()->all();
    }

    private function batchCode(int $movementId): string
    {
        return 'PM-' . $movementId;
    }
}
