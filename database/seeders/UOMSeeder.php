<?php

namespace Database\Seeders;

use App\Helpers\GenerateUniqeCode;
use App\Models\UnitOfMeasurement;
use App\Models\UomCategory;
use Database\Factories\UOMFactory;
use Illuminate\Database\Seeder;

class UOMSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Strategy:
     *  1. Upsert all required UOM categories (idempotent).
     *  2. For each category, create the base unit first so its ID is known.
     *  3. Create all non-base units referencing the base unit ID.
     */
    public function run(): void
    {
        $faker = fake();
        $uomDefinitions = UOMFactory::$uoms;

        // --- Step 1: collect unique category names and upsert them ----------
        $categoryNames = array_unique(array_column($uomDefinitions, 'category'));

        $categoryMap = []; // category name → UomCategory model

        foreach ($categoryNames as $categoryName) {
            $categoryMap[$categoryName] = UomCategory::firstOrCreate(
                ['name' => $categoryName],
                ['description' => null]
            );
        }

        // --- Step 2: group UOMs by category and insert base units first ------
        $byCategory = [];
        foreach ($uomDefinitions as $def) {
            $byCategory[$def['category']][] = $def;
        }

        $baseUomIdMap = []; // category name → base UnitOfMeasurement ID

        foreach ($byCategory as $categoryName => $units) {
            $category = $categoryMap[$categoryName];

            // Insert the base unit first
            foreach ($units as $u) {
                if (! $u['is_base_unit']) {
                    continue;
                }

                $baseUnit = UnitOfMeasurement::firstOrCreate(
                    [
                        'name'        => $u['name'],
                        'category_id' => $category->id,
                    ],
                    [
                        'uom_code'          => GenerateUniqeCode::generate(
                            UnitOfMeasurement::class, 'uom_code', 8, 'UOM'
                        ),
                        'symbol'            => $u['symbol'],
                        'base_uom_id'       => null,
                        'conversion_factor' => 1.000000,
                        'is_base_unit'      => true,
                        'is_active'         => true,
                        'description'       => $faker->sentence(),
                    ]
                );

                $baseUomIdMap[$categoryName] = $baseUnit->id;
                break; // only one base unit per category
            }
        }

        // --- Step 3: insert non-base units referencing the base unit ---------
        foreach ($byCategory as $categoryName => $units) {
            $category   = $categoryMap[$categoryName];
            $baseUomId  = $baseUomIdMap[$categoryName] ?? null;

            foreach ($units as $u) {
                if ($u['is_base_unit']) {
                    continue;
                }

                UnitOfMeasurement::firstOrCreate(
                    [
                        'name'        => $u['name'],
                        'category_id' => $category->id,
                    ],
                    [
                        'uom_code'          => GenerateUniqeCode::generate(
                            UnitOfMeasurement::class, 'uom_code', 8, 'UOM'
                        ),
                        'symbol'            => $u['symbol'],
                        'base_uom_id'       => $baseUomId,
                        'conversion_factor' => $u['conversion_factor'],
                        'is_base_unit'      => false,
                        'is_active'         => true,
                        'description'       => $faker->sentence(),
                    ]
                );
            }
        }
    }
}
