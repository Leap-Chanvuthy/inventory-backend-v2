<?php

namespace Database\Factories;

use App\Enums\CustomerStatusEnum;
use App\Helpers\GenerateUniqeCode;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Backward-compatible helper for seeders that call CustomerFactory::factory().
     * Prefer using Customer::factory() in Laravel 8+.
     */
    public static function factory(int $count = null, array $state = [])
    {
        $factory = static::new();

        if ($count !== null) {
            $factory = $factory->count($count);
        }

        if (!empty($state)) {
            $factory = $factory->state($state);
        }

        return $factory;
    }

    

    public function definition()
    {
        $customerStatusValue = array_map(fn($c) => $c->value, CustomerStatusEnum::cases());
        $randomId = rand(1, 30);

        return [
            'image' => "https://api.dicebear.com/9.x/adventurer/svg?seed={$randomId}",
            'fullname' => $this->faker->name(),
            'customer_code' => GenerateUniqeCode::generate(Customer::class, 'customer_code', 8, 'CUS'),
            'phone_number' => $this->faker->phoneNumber,
            'email_address' => $this->faker->unique()->email(),
            'social_media' => $this->faker->url(),
            'customer_address' => $this->faker->address(),
            'google_map_link' => $this->faker->url(),
            'customer_status' => $this->faker->randomElement($customerStatusValue),
            'customer_category_id' => $randomId,
            'customer_note' => $this->faker->paragraph(),
        ];
    }
}
