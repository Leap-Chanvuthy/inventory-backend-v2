<?php


namespace App\Service;

use App\Enums\ProductStockMovementTypeEnum;
use App\Helpers\ResponseHelper;
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

            // We'll build FIFO layers from IN movements and consume them for OUT movements
            $layers = []; // each layer: ['qty' => float, 'unit_cost' => float]

            $totalPurchaseAmount = 0.0;
            $totalPurchaseCount = 0;
            $totalReorderAmount = 0.0;
            $totalReorderCount = 0;
            $totalScrapAmount = 0.0;
            $totalScrapCount = 0;
            $salesRevenue = 0.0;
            $salesCount = 0;
            $salesCogs = 0.0;

            $getMovementTypeValue = fn($m) => is_object($m->movement_type) ? $m->movement_type->value : (string)$m->movement_type;

            // prepare counters for all movement enum types
            $typeCounts = [];
            foreach (ProductStockMovementTypeEnum::cases() as $case) {
                $typeCounts[$case->value] = 0;
            }

            foreach ($movements as $m) {
                $dir = is_object($m->direction) ? $m->direction->value : (string)$m->direction;
                $mt = $getMovementTypeValue($m);
                // increment type count
                if (array_key_exists($mt, $typeCounts)) {
                    $typeCounts[$mt]++;
                }

                if ($dir === 'IN') {
                    // incoming inventory: record layer
                    $qty = (float)($m->quantity ?? 0);
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

                    if ($mt === ProductStockMovementTypeEnum::RE_ORDER->value) {
                        $totalReorderAmount += (float)($m->purchase_total_price_in_usd ?? 0.0);
                        $totalReorderCount++;
                    }
                } else {
                    // OUT movement: could be sale or scrap or adjustment
                    $outQty = (float)($m->quantity ?? 0);

                    // Helper: consume from layers (FIFO) without persisting
                    $consume = function (&$layers, float $qtyToConsume) {
                        $consumedCost = 0.0;
                        $remaining = $qtyToConsume;
                        for ($i = 0; $i < count($layers) && $remaining > 0; $i++) {
                            $layerQty = $layers[$i]['qty'];
                            if ($layerQty <= 0) continue;
                            $take = min($layerQty, $remaining);
                            $consumedCost += $take * ($layers[$i]['unit_cost'] ?? 0.0);
                            $layers[$i]['qty'] -= $take;
                            $remaining -= $take;
                        }
                        return $consumedCost;
                    };

                    if ($mt === ProductStockMovementTypeEnum::SCRAP->value) {
                        $cost = $consume($layers, $outQty);
                        $totalScrapAmount += $cost;
                        $totalScrapCount++;
                    }

                    // sales are marked by is_sold=true — compute revenue and cogs
                    if (!empty($m->is_sold) && $m->is_sold == true) {
                        $salesCount++;
                        $salesRevenue += ((float)($m->selling_unit_price_in_usd ?? 0)) * $outQty;
                        $cogs = $consume($layers, $outQty);
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
                ],
            ];

            // For external product, total loss = purchases + reorder + scrap
            $totalLoss = $totalPurchaseAmount + $totalReorderAmount + $totalScrapAmount;
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