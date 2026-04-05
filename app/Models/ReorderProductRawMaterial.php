<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReorderProductRawMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_reorder_id',
        'raw_material_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function productReorder()
    {
        return $this->belongsTo(ProductReorder::class, 'product_reorder_id');
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }
}
