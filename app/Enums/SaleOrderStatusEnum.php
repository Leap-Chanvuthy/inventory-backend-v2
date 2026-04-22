<?php

namespace App\Enums;

enum SaleOrderStatusEnum: string
{
    case DRAFT = 'DRAFT';
    case PROCESSING = 'PROCESSING';
    case ON_HOLD = 'ON_HOLD';
    case CANCELLED = 'CANCELLED';
    case REFUNDED = 'REFUNDED';
    case COMPLETED = 'COMPLETED';
}