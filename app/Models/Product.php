<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'product_name',
        'product_sku_code',
        'barcode',
        'product_description',
        'product_category_id',
        'uom_id',
        'supplier_id',
        'warehouse_id',
    ];


    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function uom()
    {
        return $this->belongsTo(UOM::class, 'uom_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function productMovements()
    {
        return $this->hasMany(ProductMovement::class, 'product_id');
    }

    public function productRawMaterials()
    {
        return $this->hasMany(ProductRawMaterial::class, 'product_id');
    }

}
