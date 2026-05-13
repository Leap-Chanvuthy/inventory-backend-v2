<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMovementAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_movement_id',
        'source_movement_id',
        'allocated_quantity',
        'selling_unit_price_in_usd',
        'selling_unit_price_in_riel',
        'cost_unit_price_in_usd',
        'cost_unit_price_in_riel',
        'selling_exchange_rate_from_usd_to_riel',
        'selling_exchange_rate_from_riel_to_usd',
        'cost_exchange_rate_from_usd_to_riel',
        'cost_exchange_rate_from_riel_to_usd',
        'allocated_at',
        'created_by',
    ];

    protected $casts = [
        'allocated_quantity' => 'float',
        'selling_unit_price_in_usd' => 'float',
        'selling_unit_price_in_riel' => 'float',
        'cost_unit_price_in_usd' => 'float',
        'cost_unit_price_in_riel' => 'float',
        'selling_exchange_rate_from_usd_to_riel' => 'float',
        'selling_exchange_rate_from_riel_to_usd' => 'float',
        'cost_exchange_rate_from_usd_to_riel' => 'float',
        'cost_exchange_rate_from_riel_to_usd' => 'float',
        'allocated_at' => 'datetime',
    ];

    public function saleMovement()
    {
        return $this->belongsTo(ProductMovement::class, 'sale_movement_id');
    }

    public function sourceMovement()
    {
        return $this->belongsTo(ProductMovement::class, 'source_movement_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
