<?php

namespace App\DTOs;

class CustomerProfileDTO
{
    public function __construct(
        public readonly array $basic_info,
        public readonly ?array $financial,
        public readonly array $addresses,
        public readonly array $tags,
    ) {
    }

    public function toArray(): array
    {
        return [
            'basic_info' => $this->basic_info,
            'financial' => $this->financial,
            'addresses' => $this->addresses,
            'tags' => $this->tags,
        ];
    }
}  
