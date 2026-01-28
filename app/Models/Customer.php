<?php

namespace App\Models;

use App\Enums\CustomerStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'customer_status' => CustomerStatusEnum::class,
    ];


    protected $fillable = [
        'customer_code',
        'image',
        'fullname',
        'email_address',
        'phone_number',
        'social_media',
        'customer_address',
        'google_map_link',
        'customer_status',
        'customer_category_id',
        'customer_note',
    ];


    public function customerCategory()
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }


}
