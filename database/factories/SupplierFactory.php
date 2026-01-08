<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Enums\SupplierCategoryEnum;
use App\Helpers\GenerateUniqeCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition()
    {
        $categoryValues = array_map(fn($c) => $c->value, SupplierCategoryEnum::cases());
        $randomId = rand(1, 30);

        return [
            'image' => "https://avatar.iran.liara.run/public/{$randomId}",
            'official_name' => $this->faker->company,
            'supplier_code' => GenerateUniqeCode::generate(Supplier::class, 'supplier_code', 8, 'SUP'),
            'contact_person' => $this->faker->name,
            'phone' => $this->faker->phoneNumber,
            'email' => $this->faker->unique()->companyEmail,

            'legal_business_name' => $this->faker->company . " LLC",
            'tax_identification_number' => $this->faker->numerify('TAX########'),
            'business_registration_number' => $this->faker->numerify('BRN########'),
            'supplier_category' => $this->faker->randomElement($categoryValues),
            'business_description' => $this->faker->paragraph,

            'address_line1' => $this->faker->streetAddress,
            'address_line2' => $this->faker->secondaryAddress,
            'village' => $this->faker->word,
            'commune' => $this->faker->word,
            'district' => $this->faker->word,
            'city' => $this->faker->city,
            'province' => $this->faker->state,
            'postal_code' => $this->faker->postcode,
            'latitude' => $this->faker->latitude(10.0, 13.5),   // approximate Cambodia latitude
            'longitude' => $this->faker->longitude(102.0, 107.0), // approximate Cambodia longitude
        ];
    }
}
