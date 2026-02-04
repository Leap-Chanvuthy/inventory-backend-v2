<?php

namespace Database\Factories;

use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Models\RMStockMovement;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RMStockMovement>
 */
class RMStockMovementFactory extends Factory
{
    protected $model = RMStockMovement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rawMaterial = RawMaterial::query()->inRandomOrder()->first() ?? RawMaterial::factory()->create();

        $movementType = $this->faker->randomElement(array_map(
            fn ($c) => $c->value,
            RawMaterialStockMovementTypeEnum::cases()
        ));

        // Enforce: only one PURCHASE movement per raw material.
        if ($movementType === RawMaterialStockMovementTypeEnum::PURCHASE->value) {
            $purchaseExists = RMStockMovement::query()
                ->where('raw_material_id', $rawMaterial->id)
                ->where('movement_type', RawMaterialStockMovementTypeEnum::PURCHASE->value)
                ->exists();

            if ($purchaseExists) {
                $movementType = RawMaterialStockMovementTypeEnum::RE_ORDER->value;
            }
        }

        // Direction should follow movement type (keeps data meaningful)
        $direction = match ($movementType) {
            RawMaterialStockMovementTypeEnum::PURCHASE->value => 'IN',
            RawMaterialStockMovementTypeEnum::RE_ORDER->value => 'IN',
            RawMaterialStockMovementTypeEnum::ADJUSTMENT_IN->value => 'IN',
            RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value => 'OUT',
            RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value => 'OUT',
            RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value => 'OUT',
            default => 'IN',
        };

        // Prevent negative stock for seeded data: OUT must not exceed current available stock.
        $availableQty = (float) RMStockMovement::query()
            ->where('raw_material_id', $rawMaterial->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END), 0) as qty")
            ->value('qty');

        if ($availableQty < 0) {
            $availableQty = 0;
        }

        if ($direction === 'OUT') {
            if ($availableQty <= 0) {
                // If nothing available, switch to an IN movement so seed remains valid.
                $movementType = RawMaterialStockMovementTypeEnum::RE_ORDER->value;
                $direction = 'IN';
                $quantity = (float) $this->faker->randomFloat(4, 1, 200);
            } else {
                $maxOut = min($availableQty, 200);
                $quantity = (float) $this->faker->randomFloat(4, 1, $maxOut);
            }
        } else {
            $quantity = (float) $this->faker->randomFloat(4, 1, 500);
        }

        $zeroPriceTypes = [
            RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value,
            RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
            RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value,
        ];

        $unitUsd = (float) $this->faker->randomFloat(4, 0.1, 100);
        $totalUsd = round($unitUsd * $quantity, 4);

        $usdToRiel = (float) $this->faker->randomFloat(4, 3900, 4300);
        $rielToUsd = $usdToRiel > 0 ? round(1 / $usdToRiel, 8) : 0;

        $unitRiel = round($unitUsd * $usdToRiel, 0);
        $totalRiel = round($totalUsd * $usdToRiel, 0);

        if (in_array($movementType, $zeroPriceTypes, true)) {
            $unitUsd = 0;
            $totalUsd = 0;
            $usdToRiel = 0;
            $unitRiel = 0;
            $totalRiel = 0;
            $rielToUsd = 0;
        }

        return [
            'raw_material_id' => $rawMaterial->id,
            'supplier_id' => $rawMaterial->supplier_id,
            'quantity' => $quantity,
            'direction' => $direction,
            'movement_type' => $movementType,
            'unit_price_in_usd' => $unitUsd,
            'total_value_in_usd' => $totalUsd,
            'exchange_rate_from_usd_to_riel' => $usdToRiel,
            'unit_price_in_riel' => $unitRiel,
            'total_value_in_riel' => $totalRiel,
            'exchange_rate_from_riel_to_usd' => $rielToUsd,
            'movement_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'note' => $this->faker->optional()->sentence(),
        ];
    }
}
