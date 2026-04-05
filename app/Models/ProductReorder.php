<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReorder extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_movement_id',
        'quantity',
        'status',
        'is_finalized',
        'created_by',
        'last_updated_by',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'is_finalized' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productMovement()
    {
        return $this->belongsTo(ProductMovement::class, 'product_movement_id');
    }

    public function bomItems()
    {
        return $this->hasMany(ReorderProductRawMaterial::class, 'product_reorder_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
