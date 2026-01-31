<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    protected $fillable = [
        'warehouse_name',
        'warehouse_manager',
        'warehouse_manager_contact',
        'warehouse_manager_email',
        'warehouse_address',
        'latitude',
        'longitude',
        'warehouse_description',
    ];

    public function images(){
        return $this->hasMany(WarehouseImage::class);
    }

    public function raw_materials()
    {
        return $this->hasMany(RawMaterial::class, 'warehouse_id');
    }
}