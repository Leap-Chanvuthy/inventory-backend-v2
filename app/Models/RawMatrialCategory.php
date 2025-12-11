<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMatrialCategory extends Model
{
    use HasFactory;

    protected $table = 'raw_material_categories';
    
    protected $fillable = [
        'category_name',
        'category_code',
        'label_color',
        'description',
    ];

}
