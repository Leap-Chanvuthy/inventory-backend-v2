<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RawMaterialCategory;

class RawMaterialCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 20 raw material categories
        RawMaterialCategory::factory()->count(20)->create();
    }
}
