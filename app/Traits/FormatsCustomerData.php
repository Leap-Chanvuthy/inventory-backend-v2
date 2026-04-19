<?php

namespace App\Traits;

use App\Models\Customer;

trait FormatsCustomerData
{
    protected function resolveCustomerName(Customer $customer): string
    {
        return (string) ($customer->fullname ?? 'Unknown Customer');
    }

    protected function resolveCustomerPhone(Customer $customer): string
    {
        return (string) ($customer->phone_number ?? '');
    }

    protected function resolveCustomerCategoryName(Customer $customer): ?string
    {
        return $customer->customerCategory?->category_name;
    }
}
