<?php

namespace App\Models;

use App\Enums\ProductTypeEnum;
use App\Enums\SaleMethodEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Product extends Model
{
    use HasFactory;
    use SoftDeletes;


    protected $auditInclude = [
        'product_name',
        'product_sku_code',
        'sale_method',
        'barcode',
        'product_description',
        'product_type',
        'product_category_id',
        'base_uom_id',
        'supplier_id',
        'warehouse_id',
    ];

    protected $fillable = [
        'product_name',
        'product_sku_code',
        'barcode',
        'product_description',
        'product_type',
        'product_category_id',
        'base_uom_id',
        'supplier_id',
        'warehouse_id',
        'sale_method',
    ];

    protected $casts = [
        'product_type' => ProductTypeEnum::class,
        'sale_method' => SaleMethodEnum::class,
    ];


    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * Base (smallest) unit — stock quantities are always stored in this unit.
     */
    public function baseUom()
    {
        return $this->belongsTo(UnitOfMeasurement::class, 'base_uom_id');
    }

    /**
     * Alias for baseUom() — used by eager-load calls that reference 'uom'.
     */
    public function uom()
    {
        return $this->belongsTo(UnitOfMeasurement::class, 'base_uom_id');
    }

    /**
     * Sale unit — quantities displayed to customers and on sales orders.
     */
    public function saleUom()
    {
        // Sale unit no longer stored separately; return base unit for compatibility
        return $this->belongsTo(UnitOfMeasurement::class, 'base_uom_id');
    }

    /**
     * Purchase unit — unit used on purchase orders (optional, falls back to base).
     */
    public function purchaseUom()
    {
        // Purchase unit no longer stored separately; return base unit for compatibility
        return $this->belongsTo(UnitOfMeasurement::class, 'base_uom_id');
    }

    /**
     * Convenience: returns the effective purchase UOM (falls back to base if null).
     */
    public function effectivePurchaseUom()
    {
        // Purchase and sale UOMs are unified to base UOM; return base
        return $this->baseUom;
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

    public function productImages()
    {
        return $this->hasMany(ProductImage::class, 'product_id')
            ->orderByDesc('is_primary')
            ->orderBy('id');
    }

    public function productReorders()
    {
        return $this->hasMany(ProductReorder::class, 'product_id');
    }

}
