<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleOrderItemFactory extends Factory
{
    protected $model = SaleOrderItem::class;

    public function definition(): array
    {
        $faker = $this->faker;

        $unitUsd = (float) $faker->randomFloat(2, 1, 100);
        $rate = (float) $faker->randomFloat(2, 3900, 4300);
        $qty = (float) $faker->randomFloat(2, 1, 5);

        $unitRiel = round($unitUsd * $rate, 2);
        $totalUsd = round($unitUsd * $qty, 2);
        $totalRiel = round($unitRiel * $qty, 2);

        return [
            'sale_order_id' => SaleOrder::query()->inRandomOrder()->value('id'),
            'product_id' => Product::query()->inRandomOrder()->value('id'),
            'sale_movement_id' => null,
            'quantity' => $qty,
            'refund_quantity' => null,
            'unit_price_in_usd' => $unitUsd,
            'unit_price_in_riel' => $unitRiel,
            'total_price_in_usd' => $totalUsd,
            'total_price_in_riel' => $totalRiel,
            'exchange_rate_from_usd_to_riel' => $rate,
            'exchange_rate_from_riel_to_usd' => $rate > 0 ? round(1 / $rate, 8) : 0,
            'note' => $faker->optional(0.3)->sentence(),
        ];
    }
}
