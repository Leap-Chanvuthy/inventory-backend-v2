<?php

namespace App\Service;

use App\Enums\PaymentTermEnum;
use App\Models\Customer;

class CustomerPaymentService
{
    public function getPaymentTerm(Customer $customer): PaymentTermEnum
    {
        $term = $customer->customerFinancial?->payment_terms;

        if ($term instanceof PaymentTermEnum) {
            return $term;
        }

        if (is_string($term)) {
            return PaymentTermEnum::tryFrom($term) ?? PaymentTermEnum::NET_0;
        }

        return PaymentTermEnum::NET_0;
    }
}
