<?php

namespace Database\Factories;

use App\Models\SupplierBank;
use App\Enums\PaymentMethodEnum;
use App\Helpers\GetBankingLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierBankFactory extends Factory
{
    protected $model = SupplierBank::class;

    public function definition()
    {
        $paymentMethods = array_map(fn($m) => $m->value, PaymentMethodEnum::cases());
        $method = $this->faker->randomElement($paymentMethods);
        $randomId = rand(1, 30);
        $qrcodeImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=Example={$randomId}";

        return [
            'bank_name' => $method,
            'account_number' => $this->faker->bankAccountNumber,
            'account_holder_name' => $this->faker->name,
            'payment_link' => $this->faker->url,
            'qr_code_image' => $qrcodeImageUrl,
            'bank_label' => static fn (array $attributes) => (new GetBankingLabel())
                ->getPaymentMethodLabel($attributes['bank_name'] ?? $method),
        ];
    }
}
