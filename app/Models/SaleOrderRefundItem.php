<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrderRefundItem extends Model
{
    use HasFactory;

    protected $casts = [
        'sale_order_refund_id' => 'integer',
        'sale_order_item_id' => 'integer',
        'quantity' => 'float',
        'process_return' => 'boolean',
        'process_refund' => 'boolean',
        'is_resellable' => 'boolean',
        'refund_percentage' => 'float',
        'refund_amount_in_usd' => 'float',
        'refund_amount_in_riel' => 'float',
    ];

    protected $fillable = [
        'sale_order_refund_id',
        'sale_order_item_id',
        'quantity',
        'process_return',
        'process_refund',
        'is_resellable',
        'return_action',
        'refund_percentage',
        'refund_amount_in_usd',
        'refund_amount_in_riel',
        'reason',
        'note',
    ];

    public function refund()
    {
        return $this->belongsTo(SaleOrderRefund::class, 'sale_order_refund_id');
    }

    public function saleOrderItem()
    {
        return $this->belongsTo(SaleOrderItem::class, 'sale_order_item_id');
    }
}

