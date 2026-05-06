<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubWarehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_name',
        'warehouse_manager',
        'warehouse_manager_contact',
        'warehouse_manager_email',
        'warehouse_address',
        'latitude',
        'longitude',
        'warehouse_description',
        'warehouse_id',
    ];


    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

}
