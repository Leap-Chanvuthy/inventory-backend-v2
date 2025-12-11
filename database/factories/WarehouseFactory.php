<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = \App\Models\Warehouse::class;

    public function definition()
    {
        // Cambodian cities
        $cities = [
            'Phnom Penh', 'Siem Reap', 'Battambang', 'Sihanoukville', 
            'Kampong Cham', 'Kampong Thom', 'Kandal', 'Takeo', 'Pursat'
        ];

        return [
            'warehouse_name' => $this->faker->company . ' Warehouse',
            'warehouse_manager' => $this->faker->name,
            'warehouse_manager_contact' => $this->faker->phoneNumber,
            'warehouse_manager_email' => $this->faker->unique()->safeEmail,
            'warehouse_address' => $this->faker->streetAddress . ', ' . $this->faker->randomElement($cities),
            'latitude' => $this->faker->latitude(10.0, 13.5),   // approximate Cambodia latitude
            'longitude' => $this->faker->longitude(102.0, 107.0), // approximate Cambodia longitude
            'warehouse_description' => $this->faker->sentence,
        ];
    }
}
