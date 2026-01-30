<?php

namespace App\Enums;

enum RawMaterialStatusEnum : string {
    case IN_STOCK = 'IN_STOCK';
    case LOW_STOCK = 'LOW_STOCK';
    case OUT_OF_STOCK = 'OUT_OF_STOCK';
    case EXPIRED = 'EXPIRED';
}