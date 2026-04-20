<?php

namespace App\Service;

use App\Models\Customer;

class CustomerPricingService
{
    public function calculateDiscountedPrice(Customer $customer, float $originalPrice): float
    {
        $normalizedPrice = max(0, $originalPrice);
        $discount = (float) ($customer->customerCategory?->discount_percentage ?? 0);
        $discount = max(0, min($discount, 100));

        $finalPrice = $normalizedPrice - ($normalizedPrice * ($discount / 100));

        return round($finalPrice, 2);
    }
}
