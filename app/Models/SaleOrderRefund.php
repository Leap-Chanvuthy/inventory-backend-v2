<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrderRefund extends Model
{
    use HasFactory;

    protected $casts = [
        'sale_order_id' => 'integer',
        'total_refund_amount_in_usd' => 'float',
        'total_refund_amount_in_riel' => 'float',
        'processed_by' => 'integer',
        'processed_at' => 'datetime',
    ];

    protected $fillable = [
        'sale_order_id',
        'refund_no',
        'refund_type',
        'refund_method',
        'reason_type',
        'total_refund_amount_in_usd',
        'total_refund_amount_in_riel',
        'reason',
        'note',
        'processed_at',
        'processed_by',
    ];

    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    public function items()
    {
        return $this->hasMany(SaleOrderRefundItem::class, 'sale_order_refund_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}

