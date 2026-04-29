<?php

namespace App\Enums;

enum PaymentStatusEnum: string
{
    case PAID = 'PAID';
    case INSTALLMENT = 'INSTALLMENT';
    case DEBT = 'DEBT';
}
