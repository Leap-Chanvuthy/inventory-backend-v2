<?php

namespace Database\Seeders;

use App\Enums\PaymentStatusEnum;
use App\Enums\SaleOrderStatusEnum;
use App\Enums\StockDirectionEnum;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Service\ProductStockDeductionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SaleOrderSeeder extends Seeder
{
    private const ORDER_COUNT = 40;
    private const MAX_ATTEMPTS = 500;

    public function run(): void
    {
        $this->ensurePrerequisites();

        $faker = fake();
        $stockService = app(ProductStockDeductionService::class);
        $batchToken = now()->format('YmdHis');

        $userIds = User::query()->pluck('id')->all();
        $customerIds = Customer::query()->pluck('id')->all();

        $created = 0;
        $attempts = 0;

        while ($created < self::ORDER_COUNT && $attempts < self::MAX_ATTEMPTS) {
            $attempts++;

            $userId = (int) $faker->randomElement($userIds);
            $customerId = !empty($customerIds) ? (int) $faker->randomElement($customerIds) : null;
            $orderDate = $faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d H:i:s');

            $orderStatus = $this->pickOrderStatus($faker);
            $paymentStatus = $this->pickPaymentStatus($faker, $orderStatus);

            $items = $this->buildOrderItems($faker, $stockService, $orderStatus === SaleOrderStatusEnum::COMPLETED->value);
            if (empty($items)) {
                continue;
            }

            DB::transaction(function () use (
                $faker,
                $created,
                $batchToken,
                $customerId,
                $orderDate,
                $orderStatus,
                $paymentStatus,
                $userId,
                $items,
                $stockService
            ) {
                $totals = $this->buildTotals($faker, $items);
                $returnWindowDays = $faker->numberBetween(14, 45);
                $returnValidUntil = Carbon::parse($orderDate)->addDays($returnWindowDays)->endOfDay();
                $paymentSnapshot = $this->buildPaymentSnapshot(
                    $faker,
                    (float) $totals['grand_total_amount_in_usd'],
                    (float) $totals['grand_total_amount_in_riel'],
                    $paymentStatus
                );

                $saleOrder = SaleOrder::factory()->create([
                    'order_no' => $this->generateSeedOrderNo($created + 1, $batchToken),
                    'customer_id' => $customerId,
                    'order_date' => $orderDate,
                    'return_window_days' => $returnWindowDays,
                    'return_valid_until' => $returnValidUntil,
                    'order_status' => $orderStatus,
                    'payment_status' => $paymentStatus,
                    'note' => $faker->optional(0.5)->sentence(),
                    'created_by' => $userId,
                    'last_updated_by' => $userId,

                    'tax_percentage' => $totals['tax_percentage'],
                    'tax_amount_in_usd' => $totals['tax_amount_in_usd'],
                    'tax_amount_in_riel' => $totals['tax_amount_in_riel'],
                    'sub_total_in_usd' => $totals['sub_total_in_usd'],
                    'sub_total_in_riel' => $totals['sub_total_in_riel'],
                    'grand_total_amount_in_usd' => $totals['grand_total_amount_in_usd'],
                    'grand_total_amount_in_riel' => $totals['grand_total_amount_in_riel'],
                    'discount_percentage' => $totals['discount_percentage'],
                    'discount_amount' => $totals['discount_amount'],
                    'paid_amount_in_usd' => $paymentSnapshot['paid_amount_in_usd'],
                    'paid_amount_in_riel' => $paymentSnapshot['paid_amount_in_riel'],
                    'paid_percentage' => $paymentSnapshot['paid_percentage'],
                    'remaining_balance_in_usd' => $paymentSnapshot['remaining_balance_in_usd'],
                    'remaining_balance_in_riel' => $paymentSnapshot['remaining_balance_in_riel'],
                    'total_refunded_amount_in_usd' => 0,
                    'total_refunded_amount_in_riel' => 0,
                ]);

                foreach ($items as $item) {
                    SaleOrderItem::factory()->create([
                        'sale_order_id' => $saleOrder->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'returned_quantity' => 0,
                        'refund_quantity' => null,
                        'unit_price_in_usd' => $item['unit_price_in_usd'],
                        'unit_price_in_riel' => $item['unit_price_in_riel'],
                        'total_price_in_usd' => $item['total_price_in_usd'],
                        'total_price_in_riel' => $item['total_price_in_riel'],
                        'exchange_rate_from_usd_to_riel' => $item['exchange_rate_from_usd_to_riel'],
                        'exchange_rate_from_riel_to_usd' => $item['exchange_rate_from_riel_to_usd'],
                        'note' => $item['note'],
                    ]);
                }

                // Completed orders are linked with stock OUT movements in product_movements.
                if ($orderStatus === SaleOrderStatusEnum::COMPLETED->value) {
                    $stockItems = $this->aggregateStockItems($items);
                    $stockService->deductStockForSaleOrder($stockItems, (int) $saleOrder->id, $userId, $orderDate);
                }
            });

            $created++;
        }
    }

    private function ensurePrerequisites(): void
    {
        if (User::query()->count() === 0) {
            $this->call(UserSeeder::class);
        }

        if (Customer::query()->count() === 0) {
            $this->call(CustomerSeeder::class);
        }

        if (Product::query()->count() === 0 || ProductMovement::query()->count() === 0) {
            $this->call(ProductSeeder::class);
        }
    }

    private function pickOrderStatus(\Faker\Generator $faker): string
    {
        // Bias toward completed orders so seeded data has realistic stock movement linkage.
        return $faker->randomElement([
            SaleOrderStatusEnum::COMPLETED->value,
            SaleOrderStatusEnum::COMPLETED->value,
            SaleOrderStatusEnum::COMPLETED->value,
            SaleOrderStatusEnum::PROCESSING->value,
            SaleOrderStatusEnum::ON_HOLD->value,
            SaleOrderStatusEnum::DRAFT->value,
            SaleOrderStatusEnum::CANCELLED->value,
        ]);
    }

    private function pickPaymentStatus(\Faker\Generator $faker, string $orderStatus): string
    {
        if ($orderStatus === SaleOrderStatusEnum::COMPLETED->value) {
            return $faker->randomElement([
                PaymentStatusEnum::PAID->value,
                PaymentStatusEnum::PAID->value,
                PaymentStatusEnum::INSTALLMENT->value,
                PaymentStatusEnum::DEBT->value,
            ]);
        }

        return $faker->randomElement([
            PaymentStatusEnum::INSTALLMENT->value,
            PaymentStatusEnum::DEBT->value,
            PaymentStatusEnum::PAID->value,
        ]);
    }

    private function buildOrderItems(\Faker\Generator $faker, ProductStockDeductionService $stockService, bool $forCompleted): array
    {
        $products = Product::query()
            ->whereHas('productMovements', function ($query) {
                $query->where('direction', StockDirectionEnum::IN->value);
            })
            ->inRandomOrder()
            ->limit(20)
            ->get();

        if ($products->isEmpty()) {
            return [];
        }

        $targetItemCount = $faker->numberBetween(1, 4);
        $items = [];

        foreach ($products as $product) {
            if (count($items) >= $targetItemCount) {
                break;
            }

            $availableQty = $stockService->getAvailableStock((int) $product->id);
            if ($availableQty <= 0.01) {
                continue;
            }

            $priceSource = ProductMovement::query()
                ->where('product_id', $product->id)
                ->where('direction', StockDirectionEnum::IN->value)
                ->orderBy('movement_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if (!$priceSource) {
                continue;
            }

            // Completed orders consume stock immediately; keep quantity conservative per item.
            $maxQty = $forCompleted ? min($availableQty, 15) : min($availableQty, 25);
            if ($maxQty < 0.01) {
                continue;
            }

            $quantity = round((float) $faker->randomFloat(2, 0.5, $maxQty), 2);
            if ($quantity <= 0) {
                continue;
            }

            $unitUsd = (float) ($priceSource->selling_unit_price_in_usd ?? 0);
            $unitRiel = (float) ($priceSource->selling_unit_price_in_riel ?? 0);
            $usdToRiel = (float) ($priceSource->selling_exchange_rate_from_usd_to_riel ?? 0);
            $rielToUsd = (float) ($priceSource->selling_exchange_rate_from_riel_to_usd ?? 0);

            if ($rielToUsd <= 0 && $usdToRiel > 0) {
                $rielToUsd = round(1 / $usdToRiel, 8);
            }

            $items[] = [
                'product_id' => (int) $product->id,
                'quantity' => $quantity,
                'unit_price_in_usd' => $unitUsd,
                'unit_price_in_riel' => $unitRiel,
                'total_price_in_usd' => round($unitUsd * $quantity, 2),
                'total_price_in_riel' => round($unitRiel * $quantity, 2),
                'exchange_rate_from_usd_to_riel' => $usdToRiel,
                'exchange_rate_from_riel_to_usd' => $rielToUsd,
                'note' => $faker->optional(0.3)->sentence(),
            ];
        }

        return $items;
    }

    private function buildTotals(\Faker\Generator $faker, array $items): array
    {
        $subTotalUsd = round(array_sum(array_column($items, 'total_price_in_usd')), 2);
        $subTotalRiel = round(array_sum(array_column($items, 'total_price_in_riel')), 2);

        $discountPercentage = (float) $faker->randomElement([0, 0, 5, 10]);
        $discountAmountUsd = round($subTotalUsd * ($discountPercentage / 100), 2);
        $discountAmountRiel = round($subTotalRiel * ($discountPercentage / 100), 2);

        $taxPercentage = (float) $faker->randomElement([0, 0, 10]);
        $taxableUsd = max(0, $subTotalUsd - $discountAmountUsd);
        $taxableRiel = max(0, $subTotalRiel - $discountAmountRiel);
        $taxAmountUsd = round($taxableUsd * ($taxPercentage / 100), 2);
        $taxAmountRiel = round($taxableRiel * ($taxPercentage / 100), 2);

        return [
            'sub_total_in_usd' => $subTotalUsd,
            'sub_total_in_riel' => $subTotalRiel,
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmountUsd,
            'tax_percentage' => $taxPercentage,
            'tax_amount_in_usd' => $taxAmountUsd,
            'tax_amount_in_riel' => $taxAmountRiel,
            'grand_total_amount_in_usd' => round($taxableUsd + $taxAmountUsd, 2),
            'grand_total_amount_in_riel' => round($taxableRiel + $taxAmountRiel, 2),
        ];
    }

    private function buildPaymentSnapshot(\Faker\Generator $faker, float $grandTotalUsd, float $grandTotalRiel, string $paymentStatus): array
    {
        $grandTotalUsd = max(0, $grandTotalUsd);
        $grandTotalRiel = max(0, $grandTotalRiel);
        $rate = $grandTotalUsd > 0 ? ($grandTotalRiel / $grandTotalUsd) : 0;

        $paidUsd = 0.0;
        if ($paymentStatus === PaymentStatusEnum::PAID->value) {
            $paidUsd = $grandTotalUsd;
        } elseif (in_array($paymentStatus, [PaymentStatusEnum::DEBT->value, PaymentStatusEnum::INSTALLMENT->value], true)) {
            $paidUsd = $grandTotalUsd * (float) $faker->randomFloat(2, 0.15, 0.85);
        }

        $paidUsd = round(min($paidUsd, $grandTotalUsd), 2);
        $remainingUsd = round($grandTotalUsd - $paidUsd, 2);
        $paidRiel = round($rate > 0 ? $paidUsd * $rate : 0, 2);
        $remainingRiel = round($rate > 0 ? $remainingUsd * $rate : 0, 2);

        return [
            'paid_amount_in_usd' => $paidUsd,
            'paid_amount_in_riel' => $paidRiel,
            'paid_percentage' => $grandTotalUsd > 0 ? round(($paidUsd / $grandTotalUsd) * 100, 2) : 0,
            'remaining_balance_in_usd' => $remainingUsd,
            'remaining_balance_in_riel' => $remainingRiel,
        ];
    }

    private function aggregateStockItems(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];

            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [
                    'product_id' => $productId,
                    'quantity' => 0,
                    'unit_price_in_usd' => (float) ($item['unit_price_in_usd'] ?? 0),
                    'unit_price_in_riel' => (float) ($item['unit_price_in_riel'] ?? 0),
                    'exchange_rate_from_usd_to_riel' => (float) ($item['exchange_rate_from_usd_to_riel'] ?? 0),
                    'exchange_rate_from_riel_to_usd' => (float) ($item['exchange_rate_from_riel_to_usd'] ?? 0),
                ];
            }

            $grouped[$productId]['quantity'] += (float) $item['quantity'];
        }

        return array_values($grouped);
    }

    private function generateSeedOrderNo(int $sequence, string $batchToken): string
    {
        return sprintf('SO-SEEDED-%s-%04d', $batchToken, $sequence);
    }
}
