<?php

namespace App\DTOs;

class POSCustomerDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $phone,
        public readonly ?string $category,
        public readonly string $available_credit,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'category' => $this->category,
            'available_credit' => $this->available_credit,
        ];
    }
}
