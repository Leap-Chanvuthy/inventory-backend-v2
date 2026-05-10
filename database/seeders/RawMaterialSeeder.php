<?php

namespace Database\Seeders;

use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Enums\UomQuantityTypeEnum;
use App\Models\RawMaterial;
use App\Models\RMImage;
use App\Models\RMStockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class RawMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $faker = fake();

        // Create some raw materials (SKU uses Category + UOM + Random)
        RawMaterial::factory()->count(50)->create();

        // Ensure ALL raw materials have MANY movements and supplier consistency
        RawMaterial::query()->chunk(100, function ($rawMaterials) use ($faker) {
            foreach ($rawMaterials as $rm) {
                $rm->loadMissing('baseUom.category');
                $isIntegerQuantity = $this->isIntegerQuantityType($rm);

                // Rebuild movements for deterministic, valid stock (no negative).
                RMStockMovement::query()->where('raw_material_id', $rm->id)->delete();

                $availableQty = 0.0;
                $purchaseDate = now()->subMonths($faker->numberBetween(3, 6));

                $purchaseQty = $this->generateQuantity($faker, $isIntegerQuantity, 50, 500);
                $purchaseUnitUsd = (float) $faker->randomFloat(4, 0.1, 100);
                $purchaseTotalUsd = round($purchaseUnitUsd * $purchaseQty, 4);

                $purchaseUsdToRiel = (float) $faker->randomFloat(4, 3900, 4300);
                $purchaseRielToUsd = $purchaseUsdToRiel > 0 ? round(1 / $purchaseUsdToRiel, 8) : 0;
                $purchaseUnitRiel = round($purchaseUnitUsd * $purchaseUsdToRiel, 0);
                $purchaseTotalRiel = round($purchaseTotalUsd * $purchaseUsdToRiel, 0);

                RMStockMovement::create([
                    'raw_material_id' => $rm->id,
                    'quantity' => $purchaseQty,
                    'direction' => 'IN',
                    'movement_type' => RawMaterialStockMovementTypeEnum::PURCHASE->value,
                    'movement_date' => $purchaseDate,
                    'expiry_date' => $purchaseDate->copy()->addMonths($faker->numberBetween(6, 24)),
                    'unit_price_in_usd' => $purchaseUnitUsd,
                    'total_value_in_usd' => $purchaseTotalUsd,
                    'exchange_rate_from_usd_to_riel' => $purchaseUsdToRiel,
                    'unit_price_in_riel' => $purchaseUnitRiel,
                    'total_value_in_riel' => $purchaseTotalRiel,
                    'exchange_rate_from_riel_to_usd' => $purchaseRielToUsd,
                    'created_by' => User::query()->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                    'last_updated_by' => User::query()->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                    'note' => $faker->optional()->sentence(),
                ]);

                $availableQty += $purchaseQty;

                $targetCount = 5;
                $lastDate = Carbon::parse($purchaseDate);

                $extraTypes = [
                    RawMaterialStockMovementTypeEnum::RE_ORDER->value,
                    RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value,
                    RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
                    RawMaterialStockMovementTypeEnum::ADJUSTMENT_IN->value,
                    RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value,
                ];

                $zeroPriceTypes = [
                    RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value,
                    RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
                    RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value,
                ];

                for ($i = 1; $i < $targetCount; $i++) {
                    $type = $faker->randomElement($extraTypes);
                    $movementDate = $lastDate->copy()->addMonths(1);
                    $lastDate = $movementDate;

                    $direction = match ($type) {
                        RawMaterialStockMovementTypeEnum::RE_ORDER->value => 'IN',
                        RawMaterialStockMovementTypeEnum::ADJUSTMENT_IN->value => 'IN',
                        RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value => 'OUT',
                        RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value => 'OUT',
                        RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value => 'OUT',
                        default => 'IN',
                    };

                    $qty = $this->generateQuantity($faker, $isIntegerQuantity, 1, 200);

                    if ($direction === 'OUT') {
                        if ($availableQty <= 0) {
                            $type = RawMaterialStockMovementTypeEnum::RE_ORDER->value;
                            $direction = 'IN';
                        } else {
                            $qty = min($qty, $availableQty);
                            if ($qty <= 0) {
                                continue;
                            }
                        }
                    }

                    if (in_array($type, $zeroPriceTypes, true)) {
                        RMStockMovement::create([
                            'raw_material_id' => $rm->id,
                            'quantity' => $qty,
                            'direction' => $direction,
                            'movement_type' => $type,
                            'expiry_date' => $purchaseDate->copy()->addMonths($faker->numberBetween(6, 24)),
                            'movement_date' => $movementDate,
                            'unit_price_in_usd' => 0,
                            'total_value_in_usd' => 0,
                            'exchange_rate_from_usd_to_riel' => 0,
                            'unit_price_in_riel' => 0,
                            'total_value_in_riel' => 0,
                            'exchange_rate_from_riel_to_usd' => 0,
                            'created_by' => User::query()->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                            'last_updated_by' => User::query()->inRandomOrder()->first()->id ?? User::factory()->create()->id,                            
                            'note' => $faker->optional()->sentence(),
                        ]);
                    } else {
                        $unitUsd = (float) $faker->randomFloat(4, 0.1, 100);
                        $totalUsd = round($unitUsd * $qty, 4);
                        $usdToRiel = (float) $faker->randomFloat(4, 3900, 4300);
                        $rielToUsd = $usdToRiel > 0 ? round(1 / $usdToRiel, 8) : 0;
                        $unitRiel = round($unitUsd * $usdToRiel, 0);
                        $totalRiel = round($totalUsd * $usdToRiel, 0);

                        RMStockMovement::create([
                            'raw_material_id' => $rm->id,
                            'quantity' => $qty,
                            'direction' => $direction,
                            'movement_type' => $type,
                            'movement_date' => $movementDate,
                            'expiry_date' => $purchaseDate->copy()->addMonths($faker->numberBetween(6, 24)),                           
                            'unit_price_in_usd' => $unitUsd,
                            'total_value_in_usd' => $totalUsd,
                            'exchange_rate_from_usd_to_riel' => $usdToRiel,
                            'unit_price_in_riel' => $unitRiel,
                            'total_value_in_riel' => $totalRiel,
                            'exchange_rate_from_riel_to_usd' => $rielToUsd,
                            'created_by' => User::query()->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                            'last_updated_by' => User::query()->inRandomOrder()->first()->id ?? User::factory()->create()->id,
                            'note' => $faker->optional()->sentence(),
                        ]);
                    }

                    $availableQty += ($direction === 'OUT') ? (-$qty) : $qty;
                    if ($availableQty < 0) {
                        $availableQty = 0;
                    }
                }

                // 4) Ensure at least 3 images
                $imageCount = RMImage::query()->where('raw_material_id', $rm->id)->count();
                if ($imageCount < 3) {
                    RMImage::factory()->count(3 - $imageCount)->forRawMaterialId($rm->id)->create();
                }
            }
        });
    }

    private function isIntegerQuantityType(RawMaterial $rawMaterial): bool
    {
        $quantityType = $rawMaterial->baseUom?->category?->quantity_type;

        if ($quantityType instanceof UomQuantityTypeEnum) {
            return $quantityType === UomQuantityTypeEnum::INTEGER;
        }

        return strtoupper((string) $quantityType) === UomQuantityTypeEnum::INTEGER->value;
    }

    private function generateQuantity(\Faker\Generator $faker, bool $isIntegerQuantity, float $min, float $max): float
    {
        if ($isIntegerQuantity) {
            $intMin = max(1, (int) ceil($min));
            $intMax = max($intMin, (int) floor($max));
            return (float) $faker->numberBetween($intMin, $intMax);
        }

        return (float) $faker->randomFloat(4, $min, $max);
    }

}
