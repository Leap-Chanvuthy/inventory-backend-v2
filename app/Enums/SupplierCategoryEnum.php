<?php

namespace App\Enums;

enum SupplierCategoryEnum : string 
{
    case ELECTRONICS = 'ELECTRONICS';
    case FOOD = 'FOOD';
    case CLOTHING = 'CLOTHING';
    case LOGISTICS = 'LOGISTICS';
    case SERVICES = 'SERVICES';
    case PRODUCTS = 'PRODUCTS';
    case OTHERS = 'OTHERS';

}
