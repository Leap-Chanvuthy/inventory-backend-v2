<?php

namespace App\Enums;

enum PaymentStatusEnum: string
{
    case PAID = 'PAID';
    case UNPAID = 'UNPAID';
    case DEBT = 'DEBT';
}