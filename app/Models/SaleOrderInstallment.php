<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrderInstallment extends Model
{
    use HasFactory;

    protected $casts = [
        'sale_order_id' => 'integer',
        'percentage' => 'float',
        'cumulative_percentage' => 'float',
        'amount_usd' => 'float',
        'amount_riel' => 'float',
        'paid_at' => 'datetime',
        'created_by' => 'integer',
    ];

    protected $fillable = [
        'sale_order_id',
        'percentage',
        'cumulative_percentage',
        'amount_usd',
        'amount_riel',
        'paid_at',
        'note',
        'created_by',
    ];

    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
