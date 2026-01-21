<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\SupplierBank;
use App\Enums\PaymentMethodEnum;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::factory(100)->create()->each(function ($supplier) {
            $paymentMethods = array_map(static fn ($m) => $m->value, PaymentMethodEnum::cases());

            if (count($paymentMethods) === 0) {
                return;
            }

            shuffle($paymentMethods);

            // Assign 1-3 unique banks for each supplier (no duplicates per supplier)
            $banksCount = rand(1, min(3, count($paymentMethods)));
            $selectedMethods = array_slice($paymentMethods, 0, $banksCount);

            foreach ($selectedMethods as $method) {
                SupplierBank::factory()->create([
                    'supplier_id' => $supplier->id,
                    'bank_name' => $method,
                ]);
            }
        });
    }
}
