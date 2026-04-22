<?php

namespace App\Models;

use App\Enums\PaymentStatusEnum;
use App\Enums\SaleOrderStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'order_no',
        'customer_id',
        'order_date',
        'order_status',
        'payment_status',
        'note',
        'created_by',
        'last_updated_by',
        'tax_percentage',
        'tax_amount_in_usd',
        'tax_amount_in_riel',
        'sub_total_in_usd',
        'sub_total_in_riel',
        'grand_total_amount_in_usd',
        'grand_total_amount_in_riel',
        'discount_percentage',
        'discount_amount',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'order_status' => SaleOrderStatusEnum::class,
        'payment_status' => PaymentStatusEnum::class,
    ];


    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    

    public function orderItems()
    {
        return $this->hasMany(SaleOrderItem::class, 'sale_order_id');
    }

}
