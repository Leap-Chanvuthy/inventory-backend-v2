<?php

namespace App\Enums;

enum ProductStockMovementTypeEnum: string
{
    case EXTERNAL_PURCHASED = 'EXTERNAL_PURCHASED';
    case SCRAP = 'SCRAP';
    case INTERNAL_PRODUCED = 'INTERNAL_PRODUCED';
    case SALE_ORDER = 'SALE_ORDER'; 
    case RE_ORDER = 'RE_ORDER';
    case RETURN_FROM_CUSTOMER = 'RETURN_FROM_CUSTOMER';
    case ADJUSTMENT_IN = 'ADJUSTMENT_IN';
    case ADJUSTMENT_OUT = 'ADJUSTMENT_OUT';
}