<?php

namespace App\DTOs;

class CustomerSearchResultDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $phone,
        public readonly ?string $category,
        public readonly string $status,
        public readonly float $discount_percentage,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'category' => $this->category,
            'status' => $this->status,
            'discount_percentage' => $this->discount_percentage,
        ];
    }
}
