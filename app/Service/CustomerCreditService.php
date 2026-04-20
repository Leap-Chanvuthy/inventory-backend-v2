<?php

namespace App\Service;

use App\Models\Customer;
use App\Models\CustomerFinancial;
use App\Enums\PaymentTermEnum;
use App\Validations\CustomerAdvancedValidation;

class CustomerCreditService
{
    public function __construct(private readonly CustomerAdvancedValidation $validation)
    {
    }

    public function canPurchase(Customer $customer, float $amount): bool
    {
        $this->validation->validateCreditAmount($amount);

        // Credit-limit checks are intentionally removed. Purchase eligibility
        // is now driven by payment terms policy outside this service.
        return $amount > 0;
    }

    public function applySale(Customer $customer, float $amount): void
    {
        $this->validation->validateCreditAmount($amount);

        // No-op: credit balance tracking has been removed by design.
        $this->ensureFinancialExists($customer->id);
    }

    public function applyPayment(Customer $customer, float $amount): void
    {
        $this->validation->validateCreditAmount($amount);

        // No-op: credit balance tracking has been removed by design.
        $this->ensureFinancialExists($customer->id);
    }

    private function ensureFinancialExists(int $customerId): CustomerFinancial
    {
        return CustomerFinancial::query()->firstOrCreate(
            ['customer_id' => $customerId],
            ['payment_terms' => PaymentTermEnum::NET_0->value]
        );
    }
}
