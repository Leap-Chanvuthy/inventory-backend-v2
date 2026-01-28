<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Assumes customer_categories already exist (customer_category_id FK).
        Customer::factory()->count(50)->create();
    }
}
