<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'category_name',
        'label_color',
        'description',
        'discount_percentage',
    ];

    protected $casts = [
        'discount_percentage' => 'float',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class, 'customer_category_id');
    }

}
