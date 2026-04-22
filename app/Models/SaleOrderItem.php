<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_order_id',
        'product_id',
        'quantity',
        'refund_quantity',
        'unit_price_in_usd',
        'unit_price_in_riel',
        'total_price_in_usd',
        'total_price_in_riel',
        'exchange_rate_from_usd_to_riel',
        'exchange_rate_from_riel_to_usd',
        'note',
    ];
    
    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

}
