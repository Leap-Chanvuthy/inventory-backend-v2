<?php

namespace App\Enums;

enum RawMaterialStockMovementTypeEnum: string
{
    case PURCHASE = 'PURCHASE';
    case RE_ORDER = 'RE_ORDER';
    case SALE = 'SALE';
    case PRODUCTION_SCRAP = 'PRODUCTION_SCRAP';
    case PRODUCTION_RECEIPT = 'PRODUCTION_RECEIPT';
    case ADJUSTMENT_IN = 'ADJUSTMENT_IN';
    case ADJUSTMENT_OUT = 'ADJUSTMENT_OUT';
}
