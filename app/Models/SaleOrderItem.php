<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrderItem extends Model
{
    use HasFactory;

    protected $casts = [
        'id' => 'integer',
        'sale_order_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'float',
        'returned_quantity' => 'float',
        'refund_quantity' => 'float',
        'unit_price_in_usd' => 'float',
        'unit_price_in_riel' => 'float',
        'total_price_in_usd' => 'float',
        'total_price_in_riel' => 'float',
        'exchange_rate_from_usd_to_riel' => 'float',
        'exchange_rate_from_riel_to_usd' => 'float',
    ];

    protected $fillable = [
        'sale_order_id',
        'product_id',
        'quantity',
        'returned_quantity',
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

    public function refundItems()
    {
        return $this->hasMany(SaleOrderRefundItem::class, 'sale_order_item_id');
    }

}
