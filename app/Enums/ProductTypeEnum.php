<?php

namespace App\Enums;


enum ProductTypeEnum: string
{
    case EXTERNAL_PURCHASED = 'EXTERNAL_PURCHASED';
    case INTERNAL_PRODUCED = 'INTERNAL_PRODUCED';
}