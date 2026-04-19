<?php

namespace App\DTOs;

class CustomerStatsDTO
{
    public function __construct(
        public readonly float $total_spent,
        public readonly int $order_count,
        public readonly ?string $last_purchase_date,
    ) {
    }

    public function toArray(): array
    {
        return [
            'total_spent' => $this->total_spent,
            'order_count' => $this->order_count,
            'last_purchase_date' => $this->last_purchase_date,
        ];
    }
}
