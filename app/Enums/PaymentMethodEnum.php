<?php

namespace App\Enums;

enum PaymentMethodEnum: string
{
    case ABA = 'ABA';
    case ACLEDA = 'ACLEDA';
    case WING = 'WING';
    case BAKONG = 'BAKONG';
}
