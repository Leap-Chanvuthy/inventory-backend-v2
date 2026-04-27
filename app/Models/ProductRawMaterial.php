<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductRawMaterial extends Model
{
    use HasFactory;

    protected $table = 'product_raw_material';

    protected $casts = [
        'product_id' => 'integer',
        'raw_material_id' => 'integer',
        'quantity' => 'float',
        'quantity_per_unit' => 'float',
        'scrap_percentage' => 'float',
    ];

    protected $fillable = [
        'product_id',
        'raw_material_id',
        'quantity',
        'quantity_per_unit',
        'scrap_percentage',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

}
