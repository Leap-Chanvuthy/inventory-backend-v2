<?php

namespace App\DTOs;

class POSCustomerDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $phone,
        public readonly ?string $category,
        public readonly float $discount_percentage,
        public readonly string $payment_terms,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'category' => $this->category,
            'discount_percentage' => $this->discount_percentage,
            'payment_terms' => $this->payment_terms,
        ];
    }
}
