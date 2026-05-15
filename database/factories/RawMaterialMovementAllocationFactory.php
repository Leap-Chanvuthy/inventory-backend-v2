<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\RawMaterialMovementAllocation;
use App\Models\RMStockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RawMaterialMovementAllocationFactory extends Factory
{
    protected $model = RawMaterialMovementAllocation::class;

    public function definition(): array
    {
        $sourceMovementId = RMStockMovement::query()->inRandomOrder()->value('id');
        $consumerMovementId = RMStockMovement::query()->inRandomOrder()->value('id');
        $allocatedQty = (float) $this->faker->randomFloat(4, 1, 50);
        $unitCostUsd = (float) $this->faker->randomFloat(4, 0.1, 100);
        $unitCostRiel = round($unitCostUsd * 4100, 4);

        return [
            'consumer_movement_id' => $consumerMovementId,
            'source_movement_id' => $sourceMovementId,
            'product_id' => Product::query()->inRandomOrder()->value('id'),
            'product_movement_id' => ProductMovement::query()->inRandomOrder()->value('id'),
            'allocated_quantity' => $allocatedQty,
            'unit_cost_usd' => $unitCostUsd,
            'unit_cost_riel' => $unitCostRiel,
            'line_cost_usd' => round($allocatedQty * $unitCostUsd, 4),
            'line_cost_riel' => round($allocatedQty * $unitCostRiel, 4),
            'allocated_at' => now(),
            'created_by' => User::query()->inRandomOrder()->value('id'),
        ];
    }
}
