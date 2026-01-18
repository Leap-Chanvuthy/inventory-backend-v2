<?php

namespace Database\Seeders;

use App\Models\CustomerCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       CustomerCategory::factory()->count(30)->create();
    }
}
