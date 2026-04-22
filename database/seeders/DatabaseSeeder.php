<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            WarehouseSeeder::class,
            RawMaterialCategorySeeder::class,
            ProductCategorySeeder::class,
            CustomerCategorySeeder::class,
            SupplierSeeder::class,
            UOMSeeder::class,
            RawMaterialSeeder::class,
            CustomerSeeder::class,
            ProductSeeder::class,
            SaleOrderSeeder::class,
        ]);
    }
}
