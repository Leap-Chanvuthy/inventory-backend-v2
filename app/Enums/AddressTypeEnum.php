<?php

namespace App\Enums;

enum AddressTypeEnum: string
{
    case BILLING = 'billing';
    case SHIPPING = 'shipping';
}
