<?php

namespace App\Enums;


enum CustomerStatusEnum : string {
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case PROSPECTIVE = 'PROSPECTIVE';
}