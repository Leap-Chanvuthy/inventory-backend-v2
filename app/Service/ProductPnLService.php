<?php


namespace App\Service;

use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\ProductTypeEnum;
use App\Models\Product;
use App\Models\ProductMovement;
use Exception;

class ProductPnLService
{
    public function getProductPnL(int $productId)
    {
        try {
            $product = Product::findOrFail($productId);
            $movements = ProductMovement::where('product_id', $productId)
                ->orderBy('movement_date', 'asc')
                ->get();

            $saleMethodValue = is_object($product->sale_method) ? $product->sale_method->value : (string) $product->sale_method;
            $layerOrderIsLifo = strtoupper($saleMethodValue) === 'LIFO';
            // Build valuation layers from IN movements (FIFO/LIFO) for fallback COGS computations.
            $layers = []; // each layer: ['qty' => float, 'unit_cost' => float]

            $productTypeValue = is_object($product->product_type) ? $product->product_type->value : (string) $product->product_type;
            $isExternalPurchasedProduct = $productTypeValue === ProductTypeEnum::EXTERNAL_PURCHASED->value;
            $isInternalProducedProduct = $productTypeValue === ProductTypeEnum::INTERNAL_PRODUCED->value;

            $totalPurchaseAmount = 0.0;
            $totalPurchaseCount = 0;
            $totalInternalProducedAmount = 0.0;
            $totalInternalProducedCount = 0;
            $totalReorderAmount = 0.0;
            $totalReorderCount = 0;
            $totalScrapAmount = 0.0;
            $totalScrapCount = 0;
            $salesRevenue = 0.0;
            $salesCount = 0;
            $salesCogs = 0.0;

            $getMovementTypeValue = fn($m) => is_object($m->movement_type) ? $m->movement_type->value : (string)$m->movement_type;
            $getDirectionValue = fn($m) => is_object($m->direction) ? $m->direction->value : (string)$m->direction;
            $buildMovementValueUsd = function ($m) use ($getMovementTypeValue): float {
                $qty = (float) ($m->quantity ?? 0);
                $movementType = $getMovementTypeValue($m);

                if ($movementType === ProductStockMovementTypeEnum::SALE_ORDER->value) {
                    return round($qty * (float) ($m->selling_unit_price_in_usd ?? 0), 4);
                }

                if (!empty($m->purchase_total_price_in_usd)) {
                    return (float) $m->purchase_total_price_in_usd;
                }

                return round($qty * (float) ($m->purchase_unit_price_in_usd ?? 0), 4);
            };
            $buildMovementValueRiel = function ($m) use ($getMovementTypeValue): float {
                $qty = (float) ($m->quantity ?? 0);
                $movementType = $getMovementTypeValue($m);

                if ($movementType === ProductStockMovementTypeEnum::SALE_ORDER->value) {
                    return round($qty * (float) ($m->selling_unit_price_in_riel ?? 0), 4);
                }

                if (!empty($m->purchase_total_price_in_riel)) {
                    return (float) $m->purchase_total_price_in_riel;
                }

                return round($qty * (float) ($m->purchase_unit_price_in_riel ?? 0), 4);
            };
            $consumeFromLayers = function (&$layers, float $qtyToConsume) use ($layerOrderIsLifo) {
                $consumedCost = 0.0;
                $remaining = $qtyToConsume;

                if ($remaining <= 0) {
                    return 0.0;
                }

                while ($remaining > 0) {
                    $index = $layerOrderIsLifo ? (count($layers) - 1) : 0;
                    if (!isset($layers[$index])) {
                        break;
                    }

                    $layerQty = (float) ($layers[$index]['qty'] ?? 0);
                    if ($layerQty <= 0) {
                        array_splice($layers, $index, 1);
                        continue;
                    }

                    $take = min($layerQty, $remaining);
                    $consumedCost += $take * (float) ($layers[$index]['unit_cost'] ?? 0);
                    $layers[$index]['qty'] = $layerQty - $take;
                    $remaining -= $take;

                    if ($layers[$index]['qty'] <= 0) {
                        array_splice($layers, $index, 1);
                    }
                }

                return $consumedCost;
            };

            // prepare counters for all movement enum types
            $typeCounts = [];
            $movementFinancialByType = [];
            foreach (ProductStockMovementTypeEnum::cases() as $case) {
                $typeCounts[$case->value] = 0;
                $movementFinancialByType[$case->value] = [
                    'count' => 0,
                    'in_quantity' => 0.0,
                    'out_quantity' => 0.0,
                    'in_total_usd' => 0.0,
                    'out_total_usd' => 0.0,
                    'in_total_riel' => 0.0,
                    'out_total_riel' => 0.0,
                ];
            }

            foreach ($movements as $m) {
                $dir = $getDirectionValue($m);
                $mt = $getMovementTypeValue($m);
                $qty = (float)($m->quantity ?? 0);
                $movementValueUsd = $buildMovementValueUsd($m);
                $movementValueRiel = $buildMovementValueRiel($m);

                // increment type count
                if (array_key_exists($mt, $typeCounts)) {
                    $typeCounts[$mt]++;
                    $movementFinancialByType[$mt]['count']++;
                    if ($dir === 'IN') {
                        $movementFinancialByType[$mt]['in_quantity'] += $qty;
                        $movementFinancialByType[$mt]['in_total_usd'] += $movementValueUsd;
                        $movementFinancialByType[$mt]['in_total_riel'] += $movementValueRiel;
                    } else {
                        $movementFinancialByType[$mt]['out_quantity'] += $qty;
                        $movementFinancialByType[$mt]['out_total_usd'] += $movementValueUsd;
                        $movementFinancialByType[$mt]['out_total_riel'] += $movementValueRiel;
                    }
                }

                if ($dir === 'IN') {
                    // incoming inventory: record layer
                    $unitCost = 0.0;
                    if (!empty($m->purchase_unit_price_in_usd)) {
                        $unitCost = (float)$m->purchase_unit_price_in_usd;
                    } elseif (!empty($m->purchase_total_price_in_usd) && $qty > 0) {
                        $unitCost = (float)$m->purchase_total_price_in_usd / $qty;
                    }

                    if ($qty > 0) {
                        $layers[] = ['qty' => $qty, 'unit_cost' => $unitCost];
                    }

                    if ($mt === ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value) {
                        $totalPurchaseAmount += (float)($m->purchase_total_price_in_usd ?? 0.0);
                        $totalPurchaseCount++;
                    }

                    if ($mt === ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value) {
                        $totalInternalProducedAmount += (float)($m->purchase_total_price_in_usd ?? 0.0);
                        $totalInternalProducedCount++;
                    }

                    if ($mt === ProductStockMovementTypeEnum::RE_ORDER->value) {
                        $totalReorderAmount += (float)($m->purchase_total_price_in_usd ?? 0.0);
                        $totalReorderCount++;
                    }
                } else {
                    // OUT movement: could be sale or scrap or adjustment
                    $outQty = $qty;

                    if ($mt === ProductStockMovementTypeEnum::SCRAP->value) {
                        // Prefer persisted valuation on movement row; fallback to layer-based valuation.
                        $cost = (float) ($m->purchase_total_price_in_usd ?? 0);
                        if ($cost <= 0 && $outQty > 0) {
                            $cost = $consumeFromLayers($layers, $outQty);
                        }
                        $totalScrapAmount += $cost;
                        $totalScrapCount++;
                    }

                    // Sales revenue/cogs must be based on SALE_ORDER movements.
                    if ($mt === ProductStockMovementTypeEnum::SALE_ORDER->value) {
                        $salesCount++;
                        $salesRevenue += ((float)($m->selling_unit_price_in_usd ?? 0)) * $outQty;
                        // Prefer persisted movement COGS from deduction service.
                        $cogs = (float) ($m->purchase_total_price_in_usd ?? 0);
                        if ($cogs <= 0 && $outQty > 0) {
                            $cogs = $consumeFromLayers($layers, $outQty);
                        }
                        $salesCogs += $cogs;
                    }
                }
            }

            $revenueUsd = $salesRevenue;

            // determine exchange rate (USD -> KHR) by finding most recent movement with a rate
            $exchangeRate = null;
            foreach ($movements->reverse() as $mv) {
                if (!empty($mv->exchange_rate_from_usd_to_riel)) {
                    $exchangeRate = (float)$mv->exchange_rate_from_usd_to_riel;
                    break;
                }
                if (!empty($mv->selling_exchange_rate_from_usd_to_riel)) {
                    $exchangeRate = (float)$mv->selling_exchange_rate_from_usd_to_riel;
                    break;
                }
            }
            if (empty($exchangeRate) || $exchangeRate <= 0) {
                $exchangeRate = 4100; // sensible default if none present
            }

            // Keep product-type-specific cost visibility:
            // - internal product should not expose external purchase values
            // - external product should not expose internal produced values
            if ($isInternalProducedProduct) {
                $totalPurchaseAmount = 0.0;
                $totalPurchaseCount = 0;
            }
            if ($isExternalPurchasedProduct) {
                $totalInternalProducedAmount = 0.0;
                $totalInternalProducedCount = 0;
            }

            $movementFinancialByType = collect($movementFinancialByType)
                ->map(function ($row) {
                    return [
                        'count' => (int) ($row['count'] ?? 0),
                        'in_quantity' => round((float) ($row['in_quantity'] ?? 0), 4),
                        'out_quantity' => round((float) ($row['out_quantity'] ?? 0), 4),
                        'in_total_usd' => round((float) ($row['in_total_usd'] ?? 0), 2),
                        'out_total_usd' => round((float) ($row['out_total_usd'] ?? 0), 2),
                        'in_total_riel' => round((float) ($row['in_total_riel'] ?? 0), 2),
                        'out_total_riel' => round((float) ($row['out_total_riel'] ?? 0), 2),
                    ];
                })
                ->all();

            $breakdown = [
                'revenue_usd' => round($revenueUsd, 2),
                'revenue_riel' => round($revenueUsd * $exchangeRate, 2),
                'costs' => [
                    'purchase' => [
                        'count' => $totalPurchaseCount,
                        'total_usd' => round($totalPurchaseAmount, 2),
                        'total_riel' => round($totalPurchaseAmount * $exchangeRate, 2),
                    ],
                    'reorder' => [
                        'count' => $totalReorderCount,
                        'total_usd' => round($totalReorderAmount, 2),
                        'total_riel' => round($totalReorderAmount * $exchangeRate, 2),
                    ],
                    'scrap' => [
                        'count' => $totalScrapCount,
                        'total_usd' => round($totalScrapAmount, 2),
                        'total_riel' => round($totalScrapAmount * $exchangeRate, 2),
                    ],
                    'sales' => [
                        'count' => $salesCount,
                        'revenue_usd' => round($salesRevenue, 2),
                        'revenue_riel' => round($salesRevenue * $exchangeRate, 2),
                        'cogs_usd' => round($salesCogs, 2),
                        'cogs_riel' => round($salesCogs * $exchangeRate, 2),
                    ],
                    'profit_and_loss' => [
                        'product_type' => $productTypeValue,
                        'applied_sale_method' => strtoupper($saleMethodValue) === 'LIFO' ? 'LIFO' : 'FIFO',
                        'totals' => [
                            'revenue_usd' => round($salesRevenue, 2),
                            'revenue_riel' => round($salesRevenue * $exchangeRate, 2),
                            'cogs_usd' => round($salesCogs, 2),
                            'cogs_riel' => round($salesCogs * $exchangeRate, 2),
                            'gross_profit_usd' => round($salesRevenue - $salesCogs, 2),
                            'gross_profit_riel' => round(($salesRevenue - $salesCogs) * $exchangeRate, 2),
                        ],
                        'by_movement_type' => $movementFinancialByType,
                    ],
                ],
            ];

            // total loss = opening/acquisition costs + reorders + scrap
            $totalLoss = $totalPurchaseAmount + $totalInternalProducedAmount + $totalReorderAmount + $totalScrapAmount;
            // Gross profit = revenue - (COGS from sales + scrap losses)
            $grossProfit = $revenueUsd - ($salesCogs + $totalScrapAmount);

            $breakdown['total_loss_usd'] = round($totalLoss, 2);
            $breakdown['total_loss_riel'] = round($totalLoss * $exchangeRate, 2);
            $breakdown['gross_profit_usd'] = round($grossProfit, 2);
            $breakdown['gross_profit_riel'] = round($grossProfit * $exchangeRate, 2);

            // net profit (revenue - total losses)
            $netProfit = $revenueUsd - $totalLoss;
            $breakdown['net_profit_usd'] = round($netProfit, 2);
            $breakdown['net_profit_riel'] = round($netProfit * $exchangeRate, 2);

            $breakdown['counts'] = [
                'total_movements' => $movements->count(),
                // detailed by movement type
                'by_type' => $typeCounts,
            ];

            return $breakdown;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
