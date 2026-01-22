<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UOM extends Model
{
    use HasFactory;

    protected $table = 'unit_of_measurements';

    protected $fillable = [
        'uom_code',
        'name',
        'symbol',
        'uom_type',
        'description',
        'is_active',
    ];


    public function raw_materials()
    {
        // return $this->hasMany(RawMaterial::class, 'uom_id');
    }

}
