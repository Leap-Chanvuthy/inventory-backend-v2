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
        'id' => 'integer',
        'customer_id' => 'integer',
        'created_by' => 'integer',
        'last_updated_by' => 'integer',
        'order_date' => 'datetime',
        'order_status' => SaleOrderStatusEnum::class,
        'payment_status' => PaymentStatusEnum::class,
        'tax_percentage' => 'float',
        'tax_amount_in_usd' => 'float',
        'tax_amount_in_riel' => 'float',
        'sub_total_in_usd' => 'float',
        'sub_total_in_riel' => 'float',
        'grand_total_amount_in_usd' => 'float',
        'grand_total_amount_in_riel' => 'float',
        'discount_percentage' => 'float',
        'discount_amount' => 'float',
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
