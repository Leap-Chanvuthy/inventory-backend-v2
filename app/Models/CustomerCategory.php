<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_name',
        'label_color',
        'description',
    ];

}
