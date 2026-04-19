<?php

namespace App\DTOs;

class CustomerTimelineItemDTO
{
    public function __construct(
        public readonly string $source,
        public readonly string $event,
        public readonly ?array $payload,
        public readonly string $occurred_at,
    ) {
    }

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'event' => $this->event,
            'payload' => $this->payload,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
