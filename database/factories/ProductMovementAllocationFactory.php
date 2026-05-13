<?php

namespace Database\Factories;

use App\Models\ProductMovement;
use App\Models\ProductMovementAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductMovementAllocationFactory extends Factory
{
    protected $model = ProductMovementAllocation::class;

    public function definition(): array
    {
        $allocatedQuantity = (float) $this->faker->randomFloat(4, 1, 30);
        $sellingUnitUsd = (float) $this->faker->randomFloat(4, 1, 100);
        $costUnitUsd = (float) $this->faker->randomFloat(4, 1, 90);
        $rate = (float) $this->faker->randomFloat(4, 3900, 4300);
        $inverseRate = $rate > 0 ? round(1 / $rate, 8) : 0;

        return [
            'sale_movement_id' => ProductMovement::query()->inRandomOrder()->value('id'),
            'source_movement_id' => ProductMovement::query()->inRandomOrder()->value('id'),
            'allocated_quantity' => $allocatedQuantity,
            'selling_unit_price_in_usd' => $sellingUnitUsd,
            'selling_unit_price_in_riel' => round($sellingUnitUsd * $rate, 4),
            'cost_unit_price_in_usd' => $costUnitUsd,
            'cost_unit_price_in_riel' => round($costUnitUsd * $rate, 4),
            'selling_exchange_rate_from_usd_to_riel' => $rate,
            'selling_exchange_rate_from_riel_to_usd' => $inverseRate,
            'cost_exchange_rate_from_usd_to_riel' => $rate,
            'cost_exchange_rate_from_riel_to_usd' => $inverseRate,
            'allocated_at' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d H:i:s'),
            'created_by' => User::query()->inRandomOrder()->value('id'),
        ];
    }
}
