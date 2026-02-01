<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RMImage extends Model
{
    use HasFactory;

    protected $table = 'rm_images';

    protected $fillable = [
        'raw_material_id',
        'image',
    ];

    public function raw_material(){
        return $this -> belongsTo(RawMaterial::class , 'raw_material_id' , 'id');
    }

}
