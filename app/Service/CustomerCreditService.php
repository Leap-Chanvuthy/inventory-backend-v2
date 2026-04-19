<?php

namespace App\Service;

use App\Models\Customer;
use App\Models\CustomerFinancial;
use App\Validations\CustomerAdvancedValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerCreditService
{
    public function __construct(private readonly CustomerAdvancedValidation $validation)
    {
    }

    public function canPurchase(Customer $customer, float $amount): bool
    {
        $this->validation->validateCreditAmount($amount);

        $financial = $customer->customerFinancial;

        if (!$financial) {
            return $amount <= 0;
        }

        $available = (float) $financial->credit_limit - (float) $financial->current_balance;

        return $amount <= $available;
    }

    public function applySale(Customer $customer, float $amount): void
    {
        $this->validation->validateCreditAmount($amount);

        DB::transaction(function () use ($customer, $amount) {
            $financial = $this->getLockedFinancial($customer->id);
            $available = (float) $financial->credit_limit - (float) $financial->current_balance;

            if ($amount > $available) {
                throw ValidationException::withMessages([
                    'credit' => ['Credit limit exceeded for this customer.'],
                ]);
            }

            $financial->current_balance = (float) $financial->current_balance + $amount;
            $financial->save();
        });
    }

    public function applyPayment(Customer $customer, float $amount): void
    {
        $this->validation->validateCreditAmount($amount);

        DB::transaction(function () use ($customer, $amount) {
            $financial = $this->getLockedFinancial($customer->id);

            $newBalance = (float) $financial->current_balance - $amount;
            $financial->current_balance = max(0, $newBalance);
            $financial->save();
        });
    }

    private function getLockedFinancial(int $customerId): CustomerFinancial
    {
        $financial = CustomerFinancial::query()
            ->where('customer_id', $customerId)
            ->lockForUpdate()
            ->first();

        if ($financial) {
            return $financial;
        }

        CustomerFinancial::query()->create([
            'customer_id' => $customerId,
            'credit_limit' => 0,
            'current_balance' => 0,
            'payment_terms' => null,
        ]);

        return CustomerFinancial::query()
            ->where('customer_id', $customerId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
