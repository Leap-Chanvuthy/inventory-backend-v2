<?php

namespace Database\Seeders;

use App\Helpers\GenerateUniqeCode;
use App\Models\UOM;
use Database\Factories\UOMFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UOMSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $uoms = UOMFactory::$uoms;
        $faker = fake();

        foreach ($uoms as $u) {
            UOM::create([
                'uom_code' => GenerateUniqeCode::generate(
                    UOM::class,
                    'uom_code',
                    8,
                    'UOM'
                ),
                'name' => $u['name'],
                'symbol' => $u['symbol'],
                'uom_type' => $u['uom_type'],
                'description' => $faker->sentence(),
                'is_active' => rand(0, 1),
            ]);
        }
    }
}
