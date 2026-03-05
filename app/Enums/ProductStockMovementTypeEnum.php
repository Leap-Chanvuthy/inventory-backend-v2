<?php

namespace App\Enums;

enum ProductStockMovementTypeEnum: string
{
    case EXTERNAL_PURCHASED = 'EXTERNAL_PURCHASED';
    case SCRAP = 'SCRAP';
    case INTERNAL_MANUFACTURED = 'INTERNAL_MANUFACTURED';
    case RE_ORDER = 'RE_ORDER';
    case ADJUSTMENT_IN = 'ADJUSTMENT_IN';
    case ADJUSTMENT_OUT = 'ADJUSTMENT_OUT';
}