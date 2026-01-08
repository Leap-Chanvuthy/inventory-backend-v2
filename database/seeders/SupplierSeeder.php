<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\SupplierBank;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::factory(100)->create()->each(function ($supplier) {
            // Assign 1-3 banks for each supplier
            $banksCount = rand(1, 3);
            SupplierBank::factory($banksCount)->create([
                'supplier_id' => $supplier->id,
            ]);
        });
    }
}
