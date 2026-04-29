<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrderStatusHistory extends Model
{
    use HasFactory;

    protected $casts = [
        'sale_order_id' => 'integer',
        'changed_by' => 'integer',
        'changed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $fillable = [
        'sale_order_id',
        'from_status',
        'to_status',
        'note',
        'changed_at',
        'changed_by',
        'metadata',
    ];

    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
