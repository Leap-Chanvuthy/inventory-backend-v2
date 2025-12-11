<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Warehouse;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        // Create 10 warehouses
        Warehouse::factory(10)->create()->each(function ($warehouse) {
            // Each warehouse has 1-3 images
            $warehouse->images()->createMany(
                \App\Models\WarehouseImage::factory(rand(1, 3))->make()->toArray()
            );
        });
    }
}
