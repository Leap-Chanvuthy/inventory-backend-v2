<?php


namespace App\Enums;


enum ProductStatusEnum: string
{
    case DRAFT = 'DRAFT';
    case WORK_IN_PROGRESS = 'WORK_IN_PROGRESS';
    case PARTIALLY_COMPLETED = 'PARTIALLY_COMPLETED';
    case COMPLETED = 'COMPLETED';
    case BLOCKED = 'BLOCKED';
}