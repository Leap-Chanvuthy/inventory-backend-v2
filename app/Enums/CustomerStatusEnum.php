<?php

namespace App\Enums;


enum CustomerStatusEnum : string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case BLACKLISTED = 'blacklisted';
}