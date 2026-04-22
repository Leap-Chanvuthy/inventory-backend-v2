<?php

namespace Database\Factories;

use App\Enums\PaymentStatusEnum;
use App\Enums\SaleOrderStatusEnum;
use App\Models\Customer;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleOrderFactory extends Factory
{
    protected $model = SaleOrder::class;

    public function definition(): array
    {
        $faker = $this->faker;

        return [
            'order_no' => 'SO-' . now()->format('Ymd') . '-' . strtoupper($faker->bothify('####??')),
            'customer_id' => Customer::query()->inRandomOrder()->value('id'),
            'order_date' => $faker->dateTimeBetween('-4 months', 'now'),
            'order_status' => SaleOrderStatusEnum::DRAFT->value,
            'payment_status' => PaymentStatusEnum::UNPAID->value,
            'note' => $faker->optional(0.4)->sentence(),
            'created_by' => User::query()->inRandomOrder()->value('id'),
            'last_updated_by' => User::query()->inRandomOrder()->value('id'),

            'tax_percentage' => 0,
            'tax_amount_in_usd' => 0,
            'tax_amount_in_riel' => 0,
            'sub_total_in_usd' => 0,
            'sub_total_in_riel' => 0,
            'grand_total_amount_in_usd' => 0,
            'grand_total_amount_in_riel' => 0,
            'discount_percentage' => 0,
            'discount_amount' => 0,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'order_status' => SaleOrderStatusEnum::COMPLETED->value,
        ]);
    }
}
