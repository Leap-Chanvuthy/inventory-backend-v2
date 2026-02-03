<?php

namespace Database\Seeders;

use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Models\RawMaterial;
use App\Models\RMImage;
use App\Models\RMStockMovement;
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
                $supplierId = (int) $rm->supplier_id;

                // 1) Ensure exactly ONE PURCHASE
                $purchaseMovements = RMStockMovement::query()
                    ->where('raw_material_id', $rm->id)
                    ->where('movement_type', RawMaterialStockMovementTypeEnum::PURCHASE->value)
                    ->orderBy('movement_date')
                    ->get();

                if ($purchaseMovements->count() === 0) {
                    $purchaseDate = now()->subMonths($faker->numberBetween(3, 6));

                    $qty = (float) $faker->randomFloat(4, 1, 500);
                    $unitUsd = (float) $faker->randomFloat(4, 0.1, 100);
                    $totalUsd = round($unitUsd * $qty, 4);

                    $usdToRiel = (float) $faker->randomFloat(4, 3900, 4300);
                    $rielToUsd = $usdToRiel > 0 ? round(1 / $usdToRiel, 8) : 0;
                    $unitRiel = round($unitUsd * $usdToRiel, 0);
                    $totalRiel = round($totalUsd * $usdToRiel, 0);

                    RMStockMovement::create([
                        'raw_material_id' => $rm->id,
                        'supplier_id' => $supplierId,
                        'quantity' => $qty,
                        'direction' => 'IN',
                        'movement_type' => RawMaterialStockMovementTypeEnum::PURCHASE->value,
                        'movement_date' => $purchaseDate,
                        'unit_price_in_usd' => $unitUsd,
                        'total_value_in_usd' => $totalUsd,
                        'exchange_rate_from_usd_to_riel' => $usdToRiel,
                        'unit_price_in_riel' => $unitRiel,
                        'total_value_in_riel' => $totalRiel,
                        'exchange_rate_from_riel_to_usd' => $rielToUsd,
                        'note' => $faker->optional()->sentence(),
                    ]);

                    $purchaseMovements = RMStockMovement::query()
                        ->where('raw_material_id', $rm->id)
                        ->where('movement_type', RawMaterialStockMovementTypeEnum::PURCHASE->value)
                        ->orderBy('movement_date')
                        ->get();
                }

                if ($purchaseMovements->count() > 1) {
                    $purchaseMovements->slice(1)->each(function (RMStockMovement $m) {
                        $m->update(['movement_type' => RawMaterialStockMovementTypeEnum::RE_ORDER->value]);
                    });
                }

                // 2) Enforce supplier_id consistency across ALL movements
                RMStockMovement::query()
                    ->where('raw_material_id', $rm->id)
                    ->where('supplier_id', '!=', $supplierId)
                    ->update(['supplier_id' => $supplierId]);

                // 3) Ensure multiple movements with ~monthly gaps
                $targetCount = 5;
                $existingCount = RMStockMovement::query()->where('raw_material_id', $rm->id)->count();
                $missing = max(0, $targetCount - $existingCount);

                if ($missing > 0) {
                    $lastDateRaw = RMStockMovement::query()
                        ->where('raw_material_id', $rm->id)
                        ->max('movement_date');

                    $lastDate = $lastDateRaw ? Carbon::parse($lastDateRaw) : now()->subMonths(6);

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

                    for ($i = 0; $i < $missing; $i++) {
                        $type = $faker->randomElement($extraTypes);
                        $movementDate = $lastDate->copy()->addMonths($i + 1);

                        $direction = match ($type) {
                            RawMaterialStockMovementTypeEnum::RE_ORDER->value => 'IN',
                            RawMaterialStockMovementTypeEnum::ADJUSTMENT_IN->value => 'IN',
                            RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value => 'OUT',
                            RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value => 'OUT',
                            RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value => 'OUT',
                            default => 'IN',
                        };

                        $qty = (float) $faker->randomFloat(4, 1, 200);

                        if (in_array($type, $zeroPriceTypes, true)) {
                            RMStockMovement::create([
                                'raw_material_id' => $rm->id,
                                'supplier_id' => $supplierId,
                                'quantity' => $qty,
                                'direction' => $direction,
                                'movement_type' => $type,
                                'movement_date' => $movementDate,
                                'unit_price_in_usd' => 0,
                                'total_value_in_usd' => 0,
                                'exchange_rate_from_usd_to_riel' => 0,
                                'unit_price_in_riel' => 0,
                                'total_value_in_riel' => 0,
                                'exchange_rate_from_riel_to_usd' => 0,
                                'note' => $faker->optional()->sentence(),
                            ]);
                            continue;
                        }

                        $unitUsd = (float) $faker->randomFloat(4, 0.1, 100);
                        $totalUsd = round($unitUsd * $qty, 4);
                        $usdToRiel = (float) $faker->randomFloat(4, 3900, 4300);
                        $rielToUsd = $usdToRiel > 0 ? round(1 / $usdToRiel, 8) : 0;
                        $unitRiel = round($unitUsd * $usdToRiel, 0);
                        $totalRiel = round($totalUsd * $usdToRiel, 0);

                        RMStockMovement::create([
                            'raw_material_id' => $rm->id,
                            'supplier_id' => $supplierId,
                            'quantity' => $qty,
                            'direction' => $direction,
                            'movement_type' => $type,
                            'movement_date' => $movementDate,
                            'unit_price_in_usd' => $unitUsd,
                            'total_value_in_usd' => $totalUsd,
                            'exchange_rate_from_usd_to_riel' => $usdToRiel,
                            'unit_price_in_riel' => $unitRiel,
                            'total_value_in_riel' => $totalRiel,
                            'exchange_rate_from_riel_to_usd' => $rielToUsd,
                            'note' => $faker->optional()->sentence(),
                        ]);
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

}
