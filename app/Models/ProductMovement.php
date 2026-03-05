<?php

namespace App\Models;

use App\Enums\ProductStatusEnum;
use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\ProductTypeEnum;
use App\Enums\StockDirectionEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMovement extends Model
{
    use HasFactory;


    protected $casts = [

        'product_type' => ProductTypeEnum::class,
        'product_status' => ProductStatusEnum::class,
        'direction' => StockDirectionEnum::class,
        'movement_type' => ProductStockMovementTypeEnum::class,
        'is_sold' => 'boolean',

         // purchasing price

        'purchase_unit_price_in_usd' => 'float',
        'purchase_total_price_in_usd' => 'float',
        'purchase_unit_price_in_riel' => 'float',
        'purchase_total_price_in_riel' => 'float',
        'exchange_rate_from_usd_to_riel' => 'float',
        'exchange_rate_from_riel_to_usd' => 'float',

        'selling_unit_price_in_usd' => 'float',
        'selling_total_price_in_usd' => 'float',
        'selling_unit_price_in_riel' => 'float',
        'selling_total_price_in_riel' => 'float',
        'selling_exchange_rate_from_usd_to_riel' => 'float',
        'selling_exchange_rate_from_riel_to_usd' => 'float',
    ];


    protected $fillable = [
        'product_id',
        'quantity',
        'product_type',
        'product_status',
        'direction',
        'movement_type',
        'is_sold',

        // purchasing price
        'purchase_unit_price_in_usd',
        'purchase_total_price_in_usd',
        'purchase_unit_price_in_riel',
        'purchase_total_price_in_riel',
        'exchange_rate_from_usd_to_riel',
        'exchange_rate_from_riel_to_usd',

        // selling price
        'selling_unit_price_in_usd',
        'selling_total_price_in_usd',
        'selling_unit_price_in_riel',
        'selling_total_price_in_riel',
        'selling_exchange_rate_from_usd_to_riel',
        'selling_exchange_rate_from_riel_to_usd',
    ];


    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }


}
