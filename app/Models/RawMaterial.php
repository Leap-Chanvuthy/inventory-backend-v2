<?php

namespace App\Models;

use App\Enums\ProductionMethodEnum;
use App\Enums\RawMaterialStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawMaterial extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'raw_materials';

    protected $casts = [
        'status' => RawMaterialStatusEnum::class,
        'production_method' => ProductionMethodEnum::class,
        // 'expiry_date' => 'date',
    ];

    protected $fillable = [
        'material_name',
        'material_sku_code',
        'barcode',
        'minimum_stock_level',
        // 'expiry_date',
        'description',
        'production_method',
        'raw_material_category_id',
        'base_uom_id',
        'purchase_uom_id',
        'supplier_id',
        'warehouse_id',
    ];


    public function rm_category(){
        return $this -> belongsTo(RawMaterialCategory::class , 'raw_material_category_id' , 'id');
    }

    public function supplier(){
        return $this -> belongsTo(Supplier::class , 'supplier_id' , 'id');
    }

    public function warehouse(){
        return $this -> belongsTo(Warehouse::class , 'warehouse_id' , 'id');
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
     * Purchase unit — unit used on purchase orders (optional, falls back to base).
     */
    public function purchaseUom()
    {
        return $this->belongsTo(UnitOfMeasurement::class, 'purchase_uom_id');
    }

    /**
     * Convenience: returns the effective purchase UOM (falls back to base if null).
     */
    public function effectivePurchaseUom()
    {
        return $this->purchaseUom ?? $this->baseUom;
    }

    public function rm_images()
    {
        return $this->hasMany(RMImage::class, 'raw_material_id');
    }

    public function rm_stock_movements()
    {
        return $this->hasMany(RMStockMovement::class, 'raw_material_id');
    }

}