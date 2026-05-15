<?php

namespace App\Service;

use App\Enums\ProductionMethodEnum;
use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Models\RawMaterial;
use App\Models\RawMaterialMovementAllocation;
use App\Models\RMStockMovement;
use App\Service\Support\StockLotDateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RawMaterialStockAllocationService
{
    public function __construct(
        protected StockLotDateService $stockLotDateService
    ) {
    }

    public function getAvailableStock(int $rawMaterialId, bool $excludeExpired = true): float
    {
        $query = RMStockMovement::query()
            ->where('raw_material_id', $rawMaterialId)
            ->where('direction', StockDirectionEnum::IN->value)
            ->where('remaining_quantity', '>', 0);

        if ($excludeExpired) {
            $query->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $this->stockLotDateService->today()->toDateString());
            });
        }

        return round((float) $query->sum('remaining_quantity'), 4);
    }

    public function validateSufficientStock(array $items): array
    {
        $shortfalls = [];

        foreach ($items as $item) {
            $rawMaterialId = (int) ($item['raw_material_id'] ?? 0);
            $requiredQty = round((float) ($item['quantity'] ?? 0), 4);

            if ($rawMaterialId <= 0 || $requiredQty <= 0) {
                continue;
            }

            $rawMaterial = RawMaterial::query()->findOrFail($rawMaterialId);
            $availableQty = $this->getAvailableStock($rawMaterialId, true);

            if ($availableQty < $requiredQty) {
                $shortfalls[] = [
                    'raw_material_id' => $rawMaterialId,
                    'material_name' => (string) $rawMaterial->material_name,
                    'material_sku_code' => (string) $rawMaterial->material_sku_code,
                    'required_qty' => $requiredQty,
                    'available_qty' => $availableQty,
                    'shortfall_qty' => round($requiredQty - $availableQty, 4),
                ];
            }
        }

        return $shortfalls;
    }

    public function previewAllocation(RawMaterial $rawMaterial, float $quantity): array
    {
        $quantity = round($quantity, 4);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        $method = $this->resolveProductionMethod($rawMaterial);
        $sourceLots = $this->getEligibleSourceLots($rawMaterial, $method, false)->get();
        $available = round((float) $sourceLots->sum('remaining_quantity'), 4);

        if ($available <= 0) {
            return [
                'raw_material_id' => (int) $rawMaterial->id,
                'material_name' => (string) $rawMaterial->material_name,
                'production_method' => $method,
                'requested_quantity' => $quantity,
                'available_quantity' => 0,
                'can_fulfill' => false,
                'lots' => [],
                'message' => 'No eligible stock batches available.',
            ];
        }

        if ($available < $quantity) {
            return [
                'raw_material_id' => (int) $rawMaterial->id,
                'material_name' => (string) $rawMaterial->material_name,
                'production_method' => $method,
                'requested_quantity' => $quantity,
                'available_quantity' => $available,
                'can_fulfill' => false,
                'lots' => [],
                'message' => "Insufficient stock. Requested {$quantity}, available {$available}.",
            ];
        }

        $plan = $this->buildAllocationPlan($sourceLots, $quantity);

        return [
            'raw_material_id' => (int) $rawMaterial->id,
            'material_name' => (string) $rawMaterial->material_name,
            'production_method' => $method,
            'requested_quantity' => $quantity,
            'available_quantity' => $available,
            'can_fulfill' => true,
            'lots' => collect($plan)->map(function (array $row) {
                /** @var RMStockMovement $source */
                $source = $row['source_lot'];
                $allocatedQty = (float) $row['allocated_quantity'];
                $unitCostUsd = (float) ($source->unit_price_in_usd ?? 0);
                $unitCostRiel = (float) ($source->unit_price_in_riel ?? 0);

                return [
                    'source_movement_id' => (int) $source->id,
                    'movement_type' => $this->movementType($source),
                    'movement_date' => optional($source->movement_date)->toDateTimeString(),
                    'expiry_date' => $source->expiry_date?->toDateString(),
                    'allocated_quantity' => $allocatedQty,
                    'remaining_before' => (float) $source->remaining_quantity,
                    'remaining_after' => max(0, round((float) $source->remaining_quantity - $allocatedQty, 4)),
                    'unit_cost_usd' => $unitCostUsd,
                    'unit_cost_riel' => $unitCostRiel,
                    'line_cost_usd' => round($unitCostUsd * $allocatedQty, 4),
                    'line_cost_riel' => round($unitCostRiel * $allocatedQty, 4),
                ];
            })->values()->all(),
            'message' => null,
        ];
    }

    public function allocateForConsumption(
        int $rawMaterialId,
        float $quantity,
        int $userId,
        string $movementDate,
        string $movementType,
        array $context = []
    ): array {
        $quantity = round($quantity, 4);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($rawMaterialId, $quantity, $userId, $movementDate, $movementType, $context) {
            /** @var RawMaterial $rawMaterial */
            $rawMaterial = RawMaterial::query()->findOrFail($rawMaterialId);
            $method = $this->resolveProductionMethod($rawMaterial);
            $sourceLots = $this->getEligibleSourceLots($rawMaterial, $method, true)->get();

            $available = round((float) $sourceLots->sum('remaining_quantity'), 4);
            if ($available < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ["Insufficient stock for {$rawMaterial->material_name}. Requested {$quantity}, available {$available}."],
                ]);
            }

            $plan = $this->buildAllocationPlan($sourceLots, $quantity);

            $totalUsd = 0.0;
            $totalRiel = 0.0;
            foreach ($plan as $row) {
                /** @var RMStockMovement $source */
                $source = $row['source_lot'];
                $allocatedQty = (float) $row['allocated_quantity'];
                $totalUsd += round((float) ($source->unit_price_in_usd ?? 0) * $allocatedQty, 4);
                $totalRiel += round((float) ($source->unit_price_in_riel ?? 0) * $allocatedQty, 4);
            }

            $consumerMovement = RMStockMovement::query()->create([
                'raw_material_id' => (int) $rawMaterial->id,
                'source_movement_id' => count($plan) === 1 ? (int) $plan[0]['source_lot']->id : null,
                'quantity' => $quantity,
                'remaining_quantity' => 0,
                'direction' => StockDirectionEnum::OUT->value,
                'movement_type' => $movementType,
                'in_used' => false,
                'movement_date' => $this->stockLotDateService->normalizeMovementDate($movementDate),
                'expiry_date' => null,
                'unit_price_in_usd' => $quantity > 0 ? round($totalUsd / $quantity, 4) : 0,
                'total_value_in_usd' => round($totalUsd, 4),
                'exchange_rate_from_usd_to_riel' => 0,
                'unit_price_in_riel' => $quantity > 0 ? round($totalRiel / $quantity, 4) : 0,
                'total_value_in_riel' => round($totalRiel, 4),
                'exchange_rate_from_riel_to_usd' => 0,
                'created_by' => $userId,
                'last_updated_by' => $userId,
                'note' => $context['note'] ?? null,
            ]);

            $allocationRows = [];
            foreach ($plan as $row) {
                /** @var RMStockMovement $source */
                $source = $row['source_lot'];
                $allocatedQty = (float) $row['allocated_quantity'];

                $nextRemaining = max(0, round((float) $source->remaining_quantity - $allocatedQty, 4));
                $source->update([
                    'remaining_quantity' => $nextRemaining,
                    'in_used' => true,
                    'last_updated_by' => $userId,
                ]);

                $allocationRows[] = RawMaterialMovementAllocation::query()->create([
                    'consumer_movement_id' => (int) $consumerMovement->id,
                    'source_movement_id' => (int) $source->id,
                    'product_id' => $context['product_id'] ?? null,
                    'product_movement_id' => $context['product_movement_id'] ?? null,
                    'allocated_quantity' => $allocatedQty,
                    'unit_cost_usd' => (float) ($source->unit_price_in_usd ?? 0),
                    'unit_cost_riel' => (float) ($source->unit_price_in_riel ?? 0),
                    'line_cost_usd' => round((float) ($source->unit_price_in_usd ?? 0) * $allocatedQty, 4),
                    'line_cost_riel' => round((float) ($source->unit_price_in_riel ?? 0) * $allocatedQty, 4),
                    'allocated_at' => now(),
                    'created_by' => $userId,
                ]);
            }

            return [
                'consumer_movement' => $consumerMovement->fresh(),
                'allocations' => $allocationRows,
                'allocation_summary' => [
                    'production_method' => $method,
                    'total_quantity' => $quantity,
                    'total_cost_usd' => round($totalUsd, 4),
                    'total_cost_riel' => round($totalRiel, 4),
                ],
            ];
        });
    }

    public function createScrapFromSource(RawMaterial $rawMaterial, array $payload, int $userId): array
    {
        $sourceMovementId = (int) ($payload['source_movement_id'] ?? 0);
        $quantity = round((float) ($payload['quantity'] ?? 0), 4);
        $movementDate = $this->stockLotDateService->normalizeMovementDate($payload['movement_date'] ?? null);
        $reason = trim((string) ($payload['reason'] ?? ''));
        $note = trim((string) ($payload['note'] ?? ''));

        if ($sourceMovementId <= 0) {
            throw ValidationException::withMessages([
                'source_movement_id' => ['Please select a stock batch to scrap from.'],
            ]);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($rawMaterial, $sourceMovementId, $quantity, $movementDate, $reason, $note, $userId) {
            /** @var RMStockMovement|null $sourceLot */
            $sourceLot = RMStockMovement::query()
                ->where('id', $sourceMovementId)
                ->where('raw_material_id', (int) $rawMaterial->id)
                ->where('direction', StockDirectionEnum::IN->value)
                ->lockForUpdate()
                ->first();

            if (!$sourceLot) {
                throw ValidationException::withMessages([
                    'source_movement_id' => ['Selected stock batch was not found for this raw material.'],
                ]);
            }

            $this->assertLotCanBeConsumed($sourceLot, $quantity);

            $nextRemaining = max(0, round((float) $sourceLot->remaining_quantity - $quantity, 4));
            $sourceLot->update([
                'remaining_quantity' => $nextRemaining,
                'in_used' => true,
                'last_updated_by' => $userId,
            ]);

            $scrapNoteParts = ['SCRAP'];
            if ($reason !== '') {
                $scrapNoteParts[] = "REASON:{$reason}";
            }
            if ($note !== '') {
                $scrapNoteParts[] = $note;
            }

            $consumerMovement = RMStockMovement::query()->create([
                'raw_material_id' => (int) $rawMaterial->id,
                'source_movement_id' => (int) $sourceLot->id,
                'quantity' => $quantity,
                'remaining_quantity' => 0,
                'direction' => StockDirectionEnum::OUT->value,
                'movement_type' => RawMaterialStockMovementTypeEnum::SCRAP->value,
                'in_used' => false,
                'movement_date' => $movementDate,
                'expiry_date' => $sourceLot->expiry_date,
                'unit_price_in_usd' => (float) ($sourceLot->unit_price_in_usd ?? 0),
                'total_value_in_usd' => round((float) ($sourceLot->unit_price_in_usd ?? 0) * $quantity, 4),
                'exchange_rate_from_usd_to_riel' => (float) ($sourceLot->exchange_rate_from_usd_to_riel ?? 0),
                'unit_price_in_riel' => (float) ($sourceLot->unit_price_in_riel ?? 0),
                'total_value_in_riel' => round((float) ($sourceLot->unit_price_in_riel ?? 0) * $quantity, 4),
                'exchange_rate_from_riel_to_usd' => (float) ($sourceLot->exchange_rate_from_riel_to_usd ?? 0),
                'created_by' => $userId,
                'last_updated_by' => $userId,
                'note' => implode(' | ', $scrapNoteParts),
            ]);

            RawMaterialMovementAllocation::query()->create([
                'consumer_movement_id' => (int) $consumerMovement->id,
                'source_movement_id' => (int) $sourceLot->id,
                'product_id' => null,
                'product_movement_id' => null,
                'allocated_quantity' => $quantity,
                'unit_cost_usd' => (float) ($sourceLot->unit_price_in_usd ?? 0),
                'unit_cost_riel' => (float) ($sourceLot->unit_price_in_riel ?? 0),
                'line_cost_usd' => round((float) ($sourceLot->unit_price_in_usd ?? 0) * $quantity, 4),
                'line_cost_riel' => round((float) ($sourceLot->unit_price_in_riel ?? 0) * $quantity, 4),
                'allocated_at' => now(),
                'created_by' => $userId,
            ]);

            return [
                'scrap_movement' => $consumerMovement,
                'source_lot' => $sourceLot->fresh(),
            ];
        });
    }

    public function getStockLotsHierarchy(RawMaterial $rawMaterial, bool $includeChildren = true): array
    {
        $parentLots = RMStockMovement::query()
            ->where('raw_material_id', (int) $rawMaterial->id)
            ->where('direction', StockDirectionEnum::IN->value)
            ->orderBy('movement_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($parentLots->isEmpty()) {
            return [];
        }

        $parentIds = $parentLots->pluck('id')->map(fn ($id) => (int) $id)->all();

        $sourceAllocationsBySource = RawMaterialMovementAllocation::query()
            ->with([
                'consumerMovement:id,raw_material_id,movement_type,movement_date,source_movement_id',
                'product:id,product_name',
                'productMovement:id,movement_type,movement_date',
            ])
            ->whereIn('source_movement_id', $parentIds)
            ->get()
            ->groupBy('source_movement_id');

        $childMovementsBySource = RMStockMovement::query()
            ->whereIn('source_movement_id', $parentIds)
            ->where('direction', StockDirectionEnum::OUT->value)
            ->orderBy('movement_date', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy('source_movement_id');

        $rows = [];
        foreach ($parentLots as $lot) {
            $sourceId = (int) $lot->id;
            $qty = round((float) $lot->quantity, 4);
            $remaining = max(0, round((float) $lot->remaining_quantity, 4));
            $expiryDate = $lot->expiry_date?->toDateString();
            $isExpired = $this->stockLotDateService->isExpired($expiryDate);

            /** @var Collection<int, RawMaterialMovementAllocation> $allocations */
            $allocations = $sourceAllocationsBySource->get($sourceId, collect());
            /** @var Collection<int, RMStockMovement> $childMovements */
            $childMovements = $childMovementsBySource->get($sourceId, collect());

            $usedInProduction = round((float) $allocations
                ->filter(function (RawMaterialMovementAllocation $row) {
                    $movementType = $row->consumerMovement ? $this->movementType($row->consumerMovement) : null;
                    return in_array($movementType, [
                        RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
                        RawMaterialStockMovementTypeEnum::MANUFACTURING->value,
                    ], true);
                })
                ->sum('allocated_quantity'), 4);

            $scrappedQty = round((float) $allocations
                ->filter(function (RawMaterialMovementAllocation $row) {
                    $movementType = $row->consumerMovement ? $this->movementType($row->consumerMovement) : null;
                    return in_array($movementType, [
                        RawMaterialStockMovementTypeEnum::SCRAP->value,
                        RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value,
                    ], true);
                })
                ->sum('allocated_quantity'), 4);

            $children = [];
            if ($includeChildren) {
                $children = array_merge(
                    $this->mapRawMaterialAllocationChildren($allocations),
                    $this->mapRawMaterialDirectChildren($childMovements)
                );

                usort($children, function (array $a, array $b) {
                    return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
                });
            }

            $canUse = !$isExpired && $remaining > 0;

            $rows[] = [
                'id' => $sourceId,
                'batch_code' => 'RM-' . $sourceId,
                'movement_type' => $this->movementType($lot),
                'movement_date' => optional($lot->movement_date)->toDateTimeString(),
                'expiry_date' => $expiryDate,
                'is_expired' => $isExpired,
                'days_until_expiry' => $this->stockLotDateService->daysUntilExpiry($expiryDate),
                'quantity' => $qty,
                'remaining_quantity' => $remaining,
                'used_in_production_quantity' => $usedInProduction,
                'scrapped_quantity' => $scrappedQty,
                'available_quantity' => $remaining,
                'unit_cost_usd' => (float) ($lot->unit_price_in_usd ?? 0),
                'unit_cost_riel' => (float) ($lot->unit_price_in_riel ?? 0),
                'status' => $this->resolveLotStatus($qty, $remaining, $isExpired),
                'can_use_for_production' => $canUse,
                'can_scrap' => $canUse,
                'disabled_reason' => $canUse ? null : $this->resolveDisabledReason($isExpired, $remaining),
                'children' => $children,
            ];
        }

        return $rows;
    }

    public function getStockLotSummary(RawMaterial $rawMaterial): array
    {
        $lots = $this->getStockLotsHierarchy($rawMaterial, false);

        $available = 0.0;
        $expired = 0.0;
        $scrapped = 0.0;
        $usedInProduction = 0.0;

        foreach ($lots as $lot) {
            $remaining = (float) ($lot['remaining_quantity'] ?? 0);
            $scrapped += (float) ($lot['scrapped_quantity'] ?? 0);
            $usedInProduction += (float) ($lot['used_in_production_quantity'] ?? 0);

            if ((bool) ($lot['is_expired'] ?? false)) {
                $expired += $remaining;
            } else {
                $available += $remaining;
            }
        }

        $productionMethod = $rawMaterial->production_method instanceof \BackedEnum
            ? $rawMaterial->production_method->value
            : (string) ($rawMaterial->production_method ?? ProductionMethodEnum::FIFO->value);

        return [
            'available_quantity' => round($available, 4),
            'expired_quantity' => round($expired, 4),
            'scrapped_quantity' => round($scrapped, 4),
            'used_in_production_quantity' => round($usedInProduction, 4),
            'production_method' => strtoupper($productionMethod),
            'total_batches' => count($lots),
        ];
    }

    public function getScrapEligibleLots(RawMaterial $rawMaterial, bool $includeDisabled = false): array
    {
        $lots = $this->getStockLotsHierarchy($rawMaterial, false);

        if ($includeDisabled) {
            return $lots;
        }

        return array_values(array_filter($lots, function (array $lot) {
            return (bool) ($lot['can_scrap'] ?? false);
        }));
    }

    public function assertLotCanBeConsumed(RMStockMovement $lot, float $quantity): void
    {
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

    private function getEligibleSourceLots(RawMaterial $rawMaterial, string $method, bool $lockForUpdate)
    {
        $direction = strtoupper($method) === ProductionMethodEnum::LIFO->value ? 'desc' : 'asc';

        $query = RMStockMovement::query()
            ->where('raw_material_id', (int) $rawMaterial->id)
            ->where('direction', StockDirectionEnum::IN->value)
            ->where('remaining_quantity', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $this->stockLotDateService->today()->toDateString());
            })
            ->orderBy('movement_date', $direction)
            ->orderBy('id', $direction);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query;
    }

    private function resolveProductionMethod(RawMaterial $rawMaterial): string
    {
        $method = $rawMaterial->production_method instanceof \BackedEnum
            ? $rawMaterial->production_method->value
            : (string) ($rawMaterial->production_method ?? ProductionMethodEnum::FIFO->value);

        return strtoupper($method) === ProductionMethodEnum::LIFO->value
            ? ProductionMethodEnum::LIFO->value
            : ProductionMethodEnum::FIFO->value;
    }

    private function buildAllocationPlan(Collection $sourceLots, float $requiredQty): array
    {
        $remaining = round($requiredQty, 4);
        $plan = [];

        foreach ($sourceLots as $sourceLot) {
            if ($remaining <= 0) {
                break;
            }

            $available = round((float) $sourceLot->remaining_quantity, 4);
            if ($available <= 0) {
                continue;
            }

            $allocated = min($available, $remaining);
            $allocated = round($allocated, 4);
            if ($allocated <= 0) {
                continue;
            }

            $plan[] = [
                'source_lot' => $sourceLot,
                'allocated_quantity' => $allocated,
            ];

            $remaining = round($remaining - $allocated, 4);
        }

        return $plan;
    }

    private function movementType(RMStockMovement $movement): string
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

    private function mapRawMaterialAllocationChildren(Collection $allocations): array
    {
        return $allocations->map(function (RawMaterialMovementAllocation $allocation) {
            $consumer = $allocation->consumerMovement;
            $movementType = $consumer ? $this->movementType($consumer) : null;

            return [
                'id' => (int) $allocation->id,
                'type' => $movementType,
                'reference' => $consumer ? 'RM-' . (int) $consumer->id : null,
                'quantity' => (float) ($allocation->allocated_quantity ?? 0),
                'date' => optional($consumer?->movement_date)->toDateTimeString(),
                'product_id' => $allocation->product_id ? (int) $allocation->product_id : null,
                'product_name' => $allocation->product?->product_name,
                'product_movement_id' => $allocation->product_movement_id ? (int) $allocation->product_movement_id : null,
                'unit_cost_usd' => (float) ($allocation->unit_cost_usd ?? 0),
                'line_cost_usd' => (float) ($allocation->line_cost_usd ?? 0),
            ];
        })->values()->all();
    }

    private function mapRawMaterialDirectChildren(Collection $movements): array
    {
        return $movements->map(function (RMStockMovement $movement) {
            return [
                'id' => (int) $movement->id,
                'type' => $this->movementType($movement),
                'reference' => 'RM-' . (int) $movement->id,
                'quantity' => (float) ($movement->quantity ?? 0),
                'date' => optional($movement->movement_date)->toDateTimeString(),
                'reason' => $movement->note,
            ];
        })->values()->all();
    }
}
