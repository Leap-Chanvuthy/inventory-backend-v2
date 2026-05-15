<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterialMovementAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumer_movement_id',
        'source_movement_id',
        'product_id',
        'product_movement_id',
        'allocated_quantity',
        'unit_cost_usd',
        'unit_cost_riel',
        'line_cost_usd',
        'line_cost_riel',
        'allocated_at',
        'created_by',
    ];

    protected $casts = [
        'consumer_movement_id' => 'integer',
        'source_movement_id' => 'integer',
        'product_id' => 'integer',
        'product_movement_id' => 'integer',
        'allocated_quantity' => 'float',
        'unit_cost_usd' => 'float',
        'unit_cost_riel' => 'float',
        'line_cost_usd' => 'float',
        'line_cost_riel' => 'float',
        'allocated_at' => 'datetime',
        'created_by' => 'integer',
    ];

    public function consumerMovement()
    {
        return $this->belongsTo(RMStockMovement::class, 'consumer_movement_id');
    }

    public function sourceMovement()
    {
        return $this->belongsTo(RMStockMovement::class, 'source_movement_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productMovement()
    {
        return $this->belongsTo(ProductMovement::class, 'product_movement_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
