<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawMaterialCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'raw_material_categories';
    
    protected $fillable = [
        'category_name',
        'label_color',
        'description',
    ];

    public function raw_materials(){
        return $this -> hasMany(RawMaterial::class , 'raw_material_category_id' , 'id');
    }

}
