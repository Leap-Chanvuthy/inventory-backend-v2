<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SubWarehouseFactory extends Factory
{
    protected $model = \App\Models\SubWarehouse::class;

    public function definition()
    {
        $cities = [
            'Phnom Penh', 'Siem Reap', 'Battambang', 'Sihanoukville',
            'Kampong Cham', 'Kampong Thom', 'Kandal', 'Takeo', 'Pursat'
        ];

        return [
            'warehouse_name' => $this->faker->company . ' Sub Warehouse',
            'warehouse_manager' => $this->faker->name,
            'warehouse_manager_contact' => $this->faker->phoneNumber,
            'warehouse_manager_email' => $this->faker->unique()->safeEmail,
            'warehouse_address' => $this->faker->streetAddress . ', ' . $this->faker->randomElement($cities),
            'latitude' => $this->faker->latitude(10.0, 13.5),
            'longitude' => $this->faker->longitude(102.0, 107.0),
            'warehouse_description' => $this->faker->sentence,
            'warehouse_id' => \App\Models\Warehouse::factory(),
        ];
    }
}
