<?php

namespace App\Service;

use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\ProductTypeEnum;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\ProductMovementAllocation;
use App\Models\RMStockMovement;
use Exception;
use Illuminate\Support\Collection;

class ProductPnLService
{
    public function getProductPnL(int $productId): array
    {
        return $this->getDetailedProductPnL($productId);
    }

    public function getDetailedProductPnL(int $productId): array
    {
        try {
            $product = Product::query()->findOrFail($productId);

            $movements = ProductMovement::query()
                ->where('product_id', $productId)
                ->orderBy('movement_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $saleMethod = strtoupper((string) ($product->sale_method instanceof \BackedEnum
                ? $product->sale_method->value
                : ($product->sale_method ?? 'FIFO')));
            $saleMethod = $saleMethod === 'LIFO' ? 'LIFO' : 'FIFO';
            $isLifo = $saleMethod === 'LIFO';

            $productType = (string) ($product->product_type instanceof \BackedEnum
                ? $product->product_type->value
                : $product->product_type);
            $isInternal = $productType === ProductTypeEnum::INTERNAL_PRODUCED->value;

            $exchangeRate = $this->resolveExchangeRate($movements);

            $inMovements = $movements->filter(function (ProductMovement $movement) {
                $direction = $movement->direction instanceof \BackedEnum ? $movement->direction->value : (string) $movement->direction;
                return $direction === 'IN';
            })->values();

            $saleMovementIds = $movements
                ->filter(function (ProductMovement $movement) {
                    $direction = $movement->direction instanceof \BackedEnum ? $movement->direction->value : (string) $movement->direction;
                    $type = $movement->movement_type instanceof \BackedEnum ? $movement->movement_type->value : (string) $movement->movement_type;
                    return $direction === 'OUT' && $type === ProductStockMovementTypeEnum::SALE_ORDER->value;
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $allocationsBySaleMovementId = empty($saleMovementIds)
                ? collect()
                : ProductMovementAllocation::query()
                    ->with('sourceMovement')
                    ->whereIn('sale_movement_id', $saleMovementIds)
                    ->get()
                    ->groupBy('sale_movement_id');

            $rawMaterialSpending = $isInternal
                ? $this->collectInternalRawMaterialSpending($inMovements)
                : [
                    'source_costs' => [],
                    'total_usd' => 0.0,
                    'total_riel' => 0.0,
                    'initial_usd' => 0.0,
                    'initial_riel' => 0.0,
                    'reorder_usd' => 0.0,
                    'reorder_riel' => 0.0,
                    'by_raw_material' => [],
                ];

            $unitCostBySourceMovement = [];
            $costSourceTypeByMovementId = [];
            $productionBatchBreakdown = [];
            $incomingValueUsd = 0.0;
            $incomingValueRiel = 0.0;

            foreach ($inMovements as $movement) {
                $movementId = (int) $movement->id;
                $qty = (float) ($movement->quantity ?? 0);
                $movementType = $movement->movement_type instanceof \BackedEnum
                    ? $movement->movement_type->value
                    : (string) $movement->movement_type;

                $derivedCost = $rawMaterialSpending['source_costs'][$movementId] ?? [
                    'total_usd' => 0.0,
                    'total_riel' => 0.0,
                    'unit_usd' => 0.0,
                    'unit_riel' => 0.0,
                ];

                $storedUnitUsd = (float) ($movement->purchase_unit_price_in_usd ?? 0);
                $storedUnitRiel = (float) ($movement->purchase_unit_price_in_riel ?? 0);
                $storedTotalUsd = (float) ($movement->purchase_total_price_in_usd ?? 0);
                $storedTotalRiel = (float) ($movement->purchase_total_price_in_riel ?? 0);

                $unitUsd = $storedUnitUsd;
                $unitRiel = $storedUnitRiel;
                $totalUsd = $storedTotalUsd;
                $totalRiel = $storedTotalRiel;
                $costSource = 'movement_pricing';

                if (($unitUsd <= 0 && $storedTotalUsd <= 0) && $isInternal) {
                    $unitUsd = (float) ($derivedCost['unit_usd'] ?? 0);
                    $totalUsd = (float) ($derivedCost['total_usd'] ?? 0);
                    $unitRiel = (float) ($derivedCost['unit_riel'] ?? 0);
                    $totalRiel = (float) ($derivedCost['total_riel'] ?? 0);
                    $costSource = $movementType === ProductStockMovementTypeEnum::RE_ORDER->value
                        ? 'internal_reorder_raw_material_cost'
                        : 'internal_production_raw_material_cost';
                } else {
                    if ($unitUsd <= 0 && $qty > 0 && $storedTotalUsd > 0) {
                        $unitUsd = $storedTotalUsd / $qty;
                    }
                    if ($unitRiel <= 0 && $qty > 0 && $storedTotalRiel > 0) {
                        $unitRiel = $storedTotalRiel / $qty;
                    }
                    if ($totalUsd <= 0 && $qty > 0 && $unitUsd > 0) {
                        $totalUsd = $qty * $unitUsd;
                    }
                    if ($totalRiel <= 0 && $qty > 0 && $unitRiel > 0) {
                        $totalRiel = $qty * $unitRiel;
                    }
                }

                $unitCostBySourceMovement[$movementId] = [
                    'unit_usd' => (float) $unitUsd,
                    'unit_riel' => (float) $unitRiel,
                    'total_usd' => (float) $totalUsd,
                    'total_riel' => (float) $totalRiel,
                    'source' => $costSource,
                ];
                $costSourceTypeByMovementId[$movementId] = $costSource;

                $incomingValueUsd += (float) $totalUsd;
                $incomingValueRiel += (float) $totalRiel;

                $productionBatchBreakdown[] = [
                    'movement_id' => $movementId,
                    'movement_type' => $movementType,
                    'movement_date' => optional($movement->movement_date)->toDateTimeString(),
                    'quantity' => $qty,
                    'remaining_quantity' => (float) ($movement->remaining_quantity ?? 0),
                    'unit_cost_usd' => round((float) $unitUsd, 4),
                    'unit_cost_riel' => round((float) $unitRiel, 4),
                    'total_cost_usd' => round((float) $totalUsd, 4),
                    'total_cost_riel' => round((float) $totalRiel, 4),
                    'cost_source' => $costSource,
                ];
            }

            $layers = $this->buildLayers($inMovements, $unitCostBySourceMovement);

            $salesRevenueUsd = 0.0;
            $salesRevenueRiel = 0.0;
            $salesCogsUsd = 0.0;
            $salesCogsRiel = 0.0;
            $salesCount = 0;
            $salesQuantity = 0.0;
            $scrapLossUsd = 0.0;
            $scrapLossRiel = 0.0;
            $scrapCount = 0;
            $otherLossUsd = 0.0;
            $otherLossRiel = 0.0;

            $movementTypeCounters = [];
            foreach (ProductStockMovementTypeEnum::cases() as $case) {
                $movementTypeCounters[$case->value] = 0;
            }

            $salesBreakdown = [];

            foreach ($movements as $movement) {
                $direction = $movement->direction instanceof \BackedEnum ? $movement->direction->value : (string) $movement->direction;
                $movementType = $movement->movement_type instanceof \BackedEnum ? $movement->movement_type->value : (string) $movement->movement_type;
                $qty = (float) ($movement->quantity ?? 0);

                if (array_key_exists($movementType, $movementTypeCounters)) {
                    $movementTypeCounters[$movementType]++;
                }

                if ($direction !== 'OUT') {
                    continue;
                }

                if ($movementType === ProductStockMovementTypeEnum::SALE_ORDER->value) {
                    $salesCount++;
                    $salesQuantity += $qty;

                    $allocationRows = $allocationsBySaleMovementId->get((int) $movement->id, collect());

                    if ($allocationRows->isNotEmpty()) {
                        $saleRevenueUsd = 0.0;
                        $saleRevenueRiel = 0.0;
                        $saleCogsUsd = 0.0;
                        $saleCogsRiel = 0.0;
                        $saleSources = [];

                        /** @var ProductMovementAllocation $allocation */
                        foreach ($allocationRows as $allocation) {
                            $allocatedQty = (float) ($allocation->allocated_quantity ?? 0);
                            $sourceMovementId = (int) $allocation->source_movement_id;

                            $unitCostUsd = (float) ($unitCostBySourceMovement[$sourceMovementId]['unit_usd'] ?? 0);
                            $unitCostRiel = (float) ($unitCostBySourceMovement[$sourceMovementId]['unit_riel'] ?? 0);
                            $unitRevenueUsd = (float) ($allocation->selling_unit_price_in_usd ?? 0);
                            $unitRevenueRiel = (float) ($allocation->selling_unit_price_in_riel ?? 0);

                            $lineRevenueUsd = $allocatedQty * $unitRevenueUsd;
                            $lineRevenueRiel = $allocatedQty * $unitRevenueRiel;
                            $lineCogsUsd = $allocatedQty * $unitCostUsd;
                            $lineCogsRiel = $allocatedQty * $unitCostRiel;

                            $saleRevenueUsd += $lineRevenueUsd;
                            $saleRevenueRiel += $lineRevenueRiel;
                            $saleCogsUsd += $lineCogsUsd;
                            $saleCogsRiel += $lineCogsRiel;

                            $this->consumeSpecificSource($layers, $sourceMovementId, $allocatedQty);

                            $saleSources[] = [
                                'source_movement_id' => $sourceMovementId,
                                'allocated_quantity' => round($allocatedQty, 4),
                                'unit_revenue_usd' => round($unitRevenueUsd, 4),
                                'unit_cost_usd' => round($unitCostUsd, 4),
                                'line_revenue_usd' => round($lineRevenueUsd, 4),
                                'line_cost_usd' => round($lineCogsUsd, 4),
                                'cost_source' => $costSourceTypeByMovementId[$sourceMovementId] ?? 'movement_pricing',
                            ];
                        }

                        $salesRevenueUsd += $saleRevenueUsd;
                        $salesRevenueRiel += $saleRevenueRiel;
                        $salesCogsUsd += $saleCogsUsd;
                        $salesCogsRiel += $saleCogsRiel;

                        $salesBreakdown[] = [
                            'sale_movement_id' => (int) $movement->id,
                            'movement_date' => optional($movement->movement_date)->toDateTimeString(),
                            'quantity' => round($qty, 4),
                            'revenue_usd' => round($saleRevenueUsd, 4),
                            'revenue_riel' => round($saleRevenueRiel, 4),
                            'cogs_usd' => round($saleCogsUsd, 4),
                            'cogs_riel' => round($saleCogsRiel, 4),
                            'gross_profit_usd' => round($saleRevenueUsd - $saleCogsUsd, 4),
                            'sources' => $saleSources,
                        ];
                    } else {
                        $lineRevenueUsd = $qty * (float) ($movement->selling_unit_price_in_usd ?? 0);
                        $lineRevenueRiel = $qty * (float) ($movement->selling_unit_price_in_riel ?? 0);

                        $consumed = $this->consumeByMethod($layers, $qty, $isLifo);

                        $salesRevenueUsd += $lineRevenueUsd;
                        $salesRevenueRiel += $lineRevenueRiel;
                        $salesCogsUsd += (float) ($consumed['cost_usd'] ?? 0);
                        $salesCogsRiel += (float) ($consumed['cost_riel'] ?? 0);

                        $salesBreakdown[] = [
                            'sale_movement_id' => (int) $movement->id,
                            'movement_date' => optional($movement->movement_date)->toDateTimeString(),
                            'quantity' => round($qty, 4),
                            'revenue_usd' => round($lineRevenueUsd, 4),
                            'revenue_riel' => round($lineRevenueRiel, 4),
                            'cogs_usd' => round((float) ($consumed['cost_usd'] ?? 0), 4),
                            'cogs_riel' => round((float) ($consumed['cost_riel'] ?? 0), 4),
                            'gross_profit_usd' => round($lineRevenueUsd - (float) ($consumed['cost_usd'] ?? 0), 4),
                            'sources' => $consumed['sources'] ?? [],
                        ];
                    }

                    continue;
                }

                if ($qty <= 0) {
                    continue;
                }

                $consumed = $this->consumeByMethod($layers, $qty, $isLifo);
                $lossUsd = (float) ($consumed['cost_usd'] ?? 0);
                $lossRiel = (float) ($consumed['cost_riel'] ?? 0);

                if ($movementType === ProductStockMovementTypeEnum::SCRAP->value) {
                    $scrapCount++;
                    $scrapLossUsd += $lossUsd;
                    $scrapLossRiel += $lossRiel;
                } else {
                    $otherLossUsd += $lossUsd;
                    $otherLossRiel += $lossRiel;
                }
            }

            $remainingInventoryQty = 0.0;
            $remainingInventoryCostUsd = 0.0;
            $remainingInventoryCostRiel = 0.0;
            foreach ($layers as $layer) {
                $remainingQty = (float) ($layer['remaining_qty'] ?? 0);
                if ($remainingQty <= 0) {
                    continue;
                }

                $remainingInventoryQty += $remainingQty;
                $remainingInventoryCostUsd += $remainingQty * (float) ($layer['unit_cost_usd'] ?? 0);
                $remainingInventoryCostRiel += $remainingQty * (float) ($layer['unit_cost_riel'] ?? 0);
            }

            $grossProfitUsd = $salesRevenueUsd - $salesCogsUsd;
            $grossProfitRiel = $salesRevenueRiel - $salesCogsRiel;
            $netProfitUsd = $salesRevenueUsd - $salesCogsUsd - $scrapLossUsd - $otherLossUsd;
            $netProfitRiel = $salesRevenueRiel - $salesCogsRiel - $scrapLossRiel - $otherLossRiel;

            $externalPurchaseIn = $inMovements->filter(function (ProductMovement $movement) {
                $movementType = $movement->movement_type instanceof \BackedEnum ? $movement->movement_type->value : (string) $movement->movement_type;
                return $movementType === ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value;
            });

            $reorderIn = $inMovements->filter(function (ProductMovement $movement) {
                $movementType = $movement->movement_type instanceof \BackedEnum ? $movement->movement_type->value : (string) $movement->movement_type;
                return $movementType === ProductStockMovementTypeEnum::RE_ORDER->value;
            });

            $internalProducedIn = $inMovements->filter(function (ProductMovement $movement) {
                $movementType = $movement->movement_type instanceof \BackedEnum ? $movement->movement_type->value : (string) $movement->movement_type;
                return $movementType === ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value;
            });

            return [
                'product' => [
                    'id' => (int) $product->id,
                    'name' => (string) $product->product_name,
                    'sku' => (string) $product->product_sku_code,
                    'product_type' => $productType,
                    'sale_method' => $saleMethod,
                ],
                'currency' => [
                    'base' => 'USD',
                    'display' => 'RIEL',
                    'usd_to_riel_rate' => round($exchangeRate, 4),
                ],
                'summary' => [
                    'revenue_usd' => round($salesRevenueUsd, 2),
                    'revenue_riel' => round($salesRevenueRiel, 2),
                    'sales_cogs_usd' => round($salesCogsUsd, 2),
                    'sales_cogs_riel' => round($salesCogsRiel, 2),
                    'scrap_loss_usd' => round($scrapLossUsd, 2),
                    'scrap_loss_riel' => round($scrapLossRiel, 2),
                    'other_loss_usd' => round($otherLossUsd, 2),
                    'other_loss_riel' => round($otherLossRiel, 2),
                    'gross_profit_usd' => round($grossProfitUsd, 2),
                    'gross_profit_riel' => round($grossProfitRiel, 2),
                    'net_profit_usd' => round($netProfitUsd, 2),
                    'net_profit_riel' => round($netProfitRiel, 2),
                    'gross_margin_pct' => $salesRevenueUsd > 0 ? round(($grossProfitUsd / $salesRevenueUsd) * 100, 2) : 0,
                    'net_margin_pct' => $salesRevenueUsd > 0 ? round(($netProfitUsd / $salesRevenueUsd) * 100, 2) : 0,
                ],
                'sales' => [
                    'count' => $salesCount,
                    'quantity' => round($salesQuantity, 4),
                    'revenue_usd' => round($salesRevenueUsd, 2),
                    'revenue_riel' => round($salesRevenueRiel, 2),
                    'cogs_usd' => round($salesCogsUsd, 2),
                    'cogs_riel' => round($salesCogsRiel, 2),
                    'gross_profit_usd' => round($grossProfitUsd, 2),
                    'gross_profit_riel' => round($grossProfitRiel, 2),
                    'lines' => $salesBreakdown,
                ],
                'inventory' => [
                    'incoming_total_qty' => round((float) $inMovements->sum('quantity'), 4),
                    'incoming_total_cost_usd' => round($incomingValueUsd, 2),
                    'incoming_total_cost_riel' => round($incomingValueRiel, 2),
                    'remaining_qty' => round($remainingInventoryQty, 4),
                    'remaining_cost_usd' => round($remainingInventoryCostUsd, 2),
                    'remaining_cost_riel' => round($remainingInventoryCostRiel, 2),
                ],
                'cost_breakdown' => [
                    'external_purchase' => [
                        'count' => $externalPurchaseIn->count(),
                        'quantity' => round((float) $externalPurchaseIn->sum('quantity'), 4),
                        'cost_usd' => round((float) $externalPurchaseIn->sum('purchase_total_price_in_usd'), 2),
                        'cost_riel' => round((float) $externalPurchaseIn->sum('purchase_total_price_in_riel'), 2),
                    ],
                    'internal_production' => [
                        'count' => $internalProducedIn->count(),
                        'quantity' => round((float) $internalProducedIn->sum('quantity'), 4),
                        'cost_usd' => round((float) $internalProducedIn->sum(function (ProductMovement $movement) use ($unitCostBySourceMovement) {
                            return (float) ($unitCostBySourceMovement[(int) $movement->id]['total_usd'] ?? 0);
                        }), 2),
                        'cost_riel' => round((float) $internalProducedIn->sum(function (ProductMovement $movement) use ($unitCostBySourceMovement) {
                            return (float) ($unitCostBySourceMovement[(int) $movement->id]['total_riel'] ?? 0);
                        }), 2),
                    ],
                    'reorder' => [
                        'count' => $reorderIn->count(),
                        'quantity' => round((float) $reorderIn->sum('quantity'), 4),
                        'cost_usd' => round((float) $reorderIn->sum(function (ProductMovement $movement) use ($unitCostBySourceMovement) {
                            return (float) ($unitCostBySourceMovement[(int) $movement->id]['total_usd'] ?? 0);
                        }), 2),
                        'cost_riel' => round((float) $reorderIn->sum(function (ProductMovement $movement) use ($unitCostBySourceMovement) {
                            return (float) ($unitCostBySourceMovement[(int) $movement->id]['total_riel'] ?? 0);
                        }), 2),
                    ],
                    'scrap' => [
                        'count' => $scrapCount,
                        'cost_usd' => round($scrapLossUsd, 2),
                        'cost_riel' => round($scrapLossRiel, 2),
                    ],
                    'other_losses' => [
                        'cost_usd' => round($otherLossUsd, 2),
                        'cost_riel' => round($otherLossRiel, 2),
                    ],
                ],
                'internal_manufacturing' => [
                    'is_internal_product' => $isInternal,
                    'raw_material_spending' => [
                        'initial_production_usd' => round((float) ($rawMaterialSpending['initial_usd'] ?? 0), 2),
                        'initial_production_riel' => round((float) ($rawMaterialSpending['initial_riel'] ?? 0), 2),
                        'reorder_usd' => round((float) ($rawMaterialSpending['reorder_usd'] ?? 0), 2),
                        'reorder_riel' => round((float) ($rawMaterialSpending['reorder_riel'] ?? 0), 2),
                        'total_usd' => round((float) ($rawMaterialSpending['total_usd'] ?? 0), 2),
                        'total_riel' => round((float) ($rawMaterialSpending['total_riel'] ?? 0), 2),
                        'by_raw_material' => array_values($rawMaterialSpending['by_raw_material'] ?? []),
                    ],
                    'production_batches' => $productionBatchBreakdown,
                ],
                'movement_counts' => [
                    'total_movements' => $movements->count(),
                    'by_type' => $movementTypeCounters,
                ],
            ];
        } catch (Exception $exception) {
            return [
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function resolveExchangeRate(Collection $movements): float
    {
        foreach ($movements->reverse() as $movement) {
            $rate = (float) ($movement->exchange_rate_from_usd_to_riel ?? 0);
            if ($rate > 0) {
                return $rate;
            }

            $sellingRate = (float) ($movement->selling_exchange_rate_from_usd_to_riel ?? 0);
            if ($sellingRate > 0) {
                return $sellingRate;
            }
        }

        return 4100.0;
    }

    private function collectInternalRawMaterialSpending(Collection $inMovements): array
    {
        $candidateMovements = $inMovements->filter(function (ProductMovement $movement) {
            $movementType = $movement->movement_type instanceof \BackedEnum ? $movement->movement_type->value : (string) $movement->movement_type;
            return in_array($movementType, [
                ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value,
                ProductStockMovementTypeEnum::RE_ORDER->value,
            ], true);
        })->values();

        if ($candidateMovements->isEmpty()) {
            return [
                'source_costs' => [],
                'total_usd' => 0.0,
                'total_riel' => 0.0,
                'initial_usd' => 0.0,
                'initial_riel' => 0.0,
                'reorder_usd' => 0.0,
                'reorder_riel' => 0.0,
                'by_raw_material' => [],
            ];
        }

        $movementTypeById = $candidateMovements
            ->mapWithKeys(function (ProductMovement $movement) {
                $movementType = $movement->movement_type instanceof \BackedEnum ? $movement->movement_type->value : (string) $movement->movement_type;
                return [(int) $movement->id => $movementType];
            })
            ->all();

        $query = RMStockMovement::query()
            ->where('direction', 'OUT')
            ->where(function ($builder) use ($movementTypeById) {
                foreach (array_keys($movementTypeById) as $movementId) {
                    $builder->orWhere('note', 'like', "%REORDER_MOVEMENT_ID:{$movementId}%");
                }
            })
            ->with(['raw_material:id,material_name,material_sku_code'])
            ->get();

        $sourceCosts = [];
        $byRawMaterial = [];
        $totalUsd = 0.0;
        $totalRiel = 0.0;
        $initialUsd = 0.0;
        $initialRiel = 0.0;
        $reorderUsd = 0.0;
        $reorderRiel = 0.0;

        foreach ($query as $row) {
            $note = (string) ($row->note ?? '');
            if (!preg_match('/REORDER_MOVEMENT_ID:(\d+)/', $note, $matches)) {
                continue;
            }

            $sourceMovementId = (int) ($matches[1] ?? 0);
            if ($sourceMovementId <= 0 || !isset($movementTypeById[$sourceMovementId])) {
                continue;
            }

            $movementType = $movementTypeById[$sourceMovementId];
            $qty = (float) ($row->quantity ?? 0);
            $lineUsd = (float) ($row->total_value_in_usd ?? 0);
            $lineRiel = (float) ($row->total_value_in_riel ?? 0);
            $rawMaterialId = (int) ($row->raw_material_id ?? 0);

            if (!isset($sourceCosts[$sourceMovementId])) {
                $sourceCosts[$sourceMovementId] = [
                    'total_qty' => 0.0,
                    'total_usd' => 0.0,
                    'total_riel' => 0.0,
                    'unit_usd' => 0.0,
                    'unit_riel' => 0.0,
                ];
            }

            $sourceCosts[$sourceMovementId]['total_qty'] += $qty;
            $sourceCosts[$sourceMovementId]['total_usd'] += $lineUsd;
            $sourceCosts[$sourceMovementId]['total_riel'] += $lineRiel;

            if ($rawMaterialId > 0) {
                if (!isset($byRawMaterial[$rawMaterialId])) {
                    $byRawMaterial[$rawMaterialId] = [
                        'raw_material_id' => $rawMaterialId,
                        'material_name' => $row->raw_material->material_name ?? null,
                        'material_sku_code' => $row->raw_material->material_sku_code ?? null,
                        'consumed_qty' => 0.0,
                        'total_usd' => 0.0,
                        'total_riel' => 0.0,
                        'initial_production_usd' => 0.0,
                        'initial_production_riel' => 0.0,
                        'reorder_usd' => 0.0,
                        'reorder_riel' => 0.0,
                    ];
                }

                $byRawMaterial[$rawMaterialId]['consumed_qty'] += $qty;
                $byRawMaterial[$rawMaterialId]['total_usd'] += $lineUsd;
                $byRawMaterial[$rawMaterialId]['total_riel'] += $lineRiel;

                if ($movementType === ProductStockMovementTypeEnum::RE_ORDER->value) {
                    $byRawMaterial[$rawMaterialId]['reorder_usd'] += $lineUsd;
                    $byRawMaterial[$rawMaterialId]['reorder_riel'] += $lineRiel;
                } else {
                    $byRawMaterial[$rawMaterialId]['initial_production_usd'] += $lineUsd;
                    $byRawMaterial[$rawMaterialId]['initial_production_riel'] += $lineRiel;
                }
            }

            $totalUsd += $lineUsd;
            $totalRiel += $lineRiel;

            if ($movementType === ProductStockMovementTypeEnum::RE_ORDER->value) {
                $reorderUsd += $lineUsd;
                $reorderRiel += $lineRiel;
            } else {
                $initialUsd += $lineUsd;
                $initialRiel += $lineRiel;
            }
        }

        foreach ($sourceCosts as $sourceMovementId => $row) {
            $movementQty = (float) ($inMovements->firstWhere('id', $sourceMovementId)?->quantity ?? 0);
            $qtyBase = $movementQty > 0 ? $movementQty : (float) ($row['total_qty'] ?? 0);

            $sourceCosts[$sourceMovementId]['unit_usd'] = $qtyBase > 0
                ? (float) $row['total_usd'] / $qtyBase
                : 0.0;
            $sourceCosts[$sourceMovementId]['unit_riel'] = $qtyBase > 0
                ? (float) $row['total_riel'] / $qtyBase
                : 0.0;
        }

        $byRawMaterial = collect($byRawMaterial)
            ->map(function (array $row) {
                $qty = (float) ($row['consumed_qty'] ?? 0);
                $row['consumed_qty'] = round($qty, 4);
                $row['total_usd'] = round((float) ($row['total_usd'] ?? 0), 2);
                $row['total_riel'] = round((float) ($row['total_riel'] ?? 0), 2);
                $row['initial_production_usd'] = round((float) ($row['initial_production_usd'] ?? 0), 2);
                $row['initial_production_riel'] = round((float) ($row['initial_production_riel'] ?? 0), 2);
                $row['reorder_usd'] = round((float) ($row['reorder_usd'] ?? 0), 2);
                $row['reorder_riel'] = round((float) ($row['reorder_riel'] ?? 0), 2);
                $row['average_unit_cost_usd'] = $qty > 0 ? round($row['total_usd'] / $qty, 4) : 0;
                $row['average_unit_cost_riel'] = $qty > 0 ? round($row['total_riel'] / $qty, 4) : 0;
                return $row;
            })
            ->sortByDesc('total_usd')
            ->values()
            ->all();

        return [
            'source_costs' => $sourceCosts,
            'total_usd' => $totalUsd,
            'total_riel' => $totalRiel,
            'initial_usd' => $initialUsd,
            'initial_riel' => $initialRiel,
            'reorder_usd' => $reorderUsd,
            'reorder_riel' => $reorderRiel,
            'by_raw_material' => $byRawMaterial,
        ];
    }

    private function buildLayers(Collection $inMovements, array $unitCostBySourceMovement): array
    {
        return $inMovements->map(function (ProductMovement $movement) use ($unitCostBySourceMovement) {
            $movementId = (int) $movement->id;
            return [
                'movement_id' => $movementId,
                'movement_date' => optional($movement->movement_date)->toDateTimeString(),
                'remaining_qty' => (float) ($movement->quantity ?? 0),
                'unit_cost_usd' => (float) ($unitCostBySourceMovement[$movementId]['unit_usd'] ?? 0),
                'unit_cost_riel' => (float) ($unitCostBySourceMovement[$movementId]['unit_riel'] ?? 0),
            ];
        })->values()->all();
    }

    private function consumeSpecificSource(array &$layers, int $sourceMovementId, float $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        foreach ($layers as $index => $layer) {
            if ((int) ($layer['movement_id'] ?? 0) !== $sourceMovementId) {
                continue;
            }

            $remaining = (float) ($layer['remaining_qty'] ?? 0);
            if ($remaining <= 0) {
                continue;
            }

            $take = min($remaining, $qty);
            $layers[$index]['remaining_qty'] = max(0, $remaining - $take);
            $qty -= $take;

            if ($qty <= 0) {
                return;
            }
        }
    }

    private function consumeByMethod(array &$layers, float $qty, bool $isLifo): array
    {
        $remaining = max(0, $qty);
        $costUsd = 0.0;
        $costRiel = 0.0;
        $sources = [];

        if ($remaining <= 0) {
            return [
                'cost_usd' => 0.0,
                'cost_riel' => 0.0,
                'sources' => [],
            ];
        }

        while ($remaining > 0) {
            $candidateIndexes = [];
            foreach ($layers as $index => $layer) {
                if ((float) ($layer['remaining_qty'] ?? 0) > 0) {
                    $candidateIndexes[] = $index;
                }
            }

            if (empty($candidateIndexes)) {
                break;
            }

            $selectedIndex = $isLifo ? end($candidateIndexes) : reset($candidateIndexes);
            $selectedLayer = $layers[$selectedIndex];
            $available = (float) ($selectedLayer['remaining_qty'] ?? 0);
            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);
            $unitUsd = (float) ($selectedLayer['unit_cost_usd'] ?? 0);
            $unitRiel = (float) ($selectedLayer['unit_cost_riel'] ?? 0);

            $costUsd += $take * $unitUsd;
            $costRiel += $take * $unitRiel;

            $layers[$selectedIndex]['remaining_qty'] = max(0, $available - $take);
            $remaining -= $take;

            $sources[] = [
                'source_movement_id' => (int) ($selectedLayer['movement_id'] ?? 0),
                'consumed_quantity' => round($take, 4),
                'unit_cost_usd' => round($unitUsd, 4),
                'unit_cost_riel' => round($unitRiel, 4),
                'line_cost_usd' => round($take * $unitUsd, 4),
                'line_cost_riel' => round($take * $unitRiel, 4),
            ];
        }

        return [
            'cost_usd' => $costUsd,
            'cost_riel' => $costRiel,
            'sources' => $sources,
        ];
    }
}
