<?php

namespace App\Models;

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
        'expiry_date' => 'date',
    ];

    protected $fillable = [
        'material_name',
        'material_sku_code',
        'barcode',
        'quantity',
        'remaining_quantity',
        'minimum_quantity_stock_level',
        'expiry_date',
        'status',
        'description',
        'raw_material_category_id',
        'uom_id',
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

    public function uom(){
        return $this -> belongsTo(UOM::class , 'uom_id' , 'id');
    }

    public function rm_stock_movements()
    {
        return $this->hasMany(RMStockMovement::class, 'raw_material_id');
    }

    public function rm_purchasing_transactions()
    {
        return $this->hasMany(RMPurchasingTransaction::class, 'raw_material_id');
    }

}