<?php

namespace Database\Factories;

use App\Enums\ProductStatusEnum;
use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductMovementFactory extends Factory
{
    protected $model = ProductMovement::class;

    public function definition(): array
    {
        $direction = $this->faker->randomElement([
            StockDirectionEnum::IN->value,
            StockDirectionEnum::OUT->value,
        ]);

        $quantity = (float) $this->faker->randomFloat(4, 1, 200);
        $remainingQuantity = $direction === StockDirectionEnum::IN->value ? $quantity : 0;

        $purchaseUnitUsd = (float) $this->faker->randomFloat(4, 1, 150);
        $sellingUnitUsd = (float) $this->faker->randomFloat(4, $purchaseUnitUsd, $purchaseUnitUsd * 2);
        $rate = (float) $this->faker->randomFloat(4, 3900, 4300);
        $inverseRate = $rate > 0 ? round(1 / $rate, 8) : 0;

        return [
            'product_id' => Product::query()->inRandomOrder()->value('id'),
            'product_status' => ProductStatusEnum::COMPLETED->value,
            'quantity' => $quantity,
            'remaining_quantity' => $remainingQuantity,
            'source_movement_id' => null,
            'is_sold' => false,
            'direction' => $direction,
            'movement_type' => $direction === StockDirectionEnum::IN->value
                ? $this->faker->randomElement([
                    ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value,
                    ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value,
                    ProductStockMovementTypeEnum::RE_ORDER->value,
                    ProductStockMovementTypeEnum::RETURN_FROM_CUSTOMER->value,
                    ProductStockMovementTypeEnum::ADJUSTMENT_IN->value,
                ])
                : $this->faker->randomElement([
                    ProductStockMovementTypeEnum::SALE_ORDER->value,
                    ProductStockMovementTypeEnum::SCRAP->value,
                    ProductStockMovementTypeEnum::ADJUSTMENT_OUT->value,
                ]),
            'purchase_unit_price_in_usd' => $purchaseUnitUsd,
            'purchase_total_price_in_usd' => round($purchaseUnitUsd * $quantity, 4),
            'purchase_unit_price_in_riel' => round($purchaseUnitUsd * $rate, 4),
            'purchase_total_price_in_riel' => round($purchaseUnitUsd * $quantity * $rate, 4),
            'exchange_rate_from_usd_to_riel' => $rate,
            'exchange_rate_from_riel_to_usd' => $inverseRate,
            'selling_unit_price_in_usd' => $sellingUnitUsd,
            'selling_unit_price_in_riel' => round($sellingUnitUsd * $rate, 4),
            'selling_exchange_rate_from_usd_to_riel' => $rate,
            'selling_exchange_rate_from_riel_to_usd' => $inverseRate,
            'movement_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d H:i:s'),
            'expiry_date' => $direction === StockDirectionEnum::IN->value
                ? $this->faker->optional(0.7)->dateTimeBetween('-15 days', '+12 months')->format('Y-m-d')
                : null,
            'note' => $this->faker->optional(0.3)->sentence(),
            'created_by' => User::query()->inRandomOrder()->value('id'),
            'last_updated_by' => User::query()->inRandomOrder()->value('id'),
        ];
    }
}
