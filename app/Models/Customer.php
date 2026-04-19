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
        'extra_data' => 'array',
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
        'extra_data',
    ];


    public function customerCategory()
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }

    public function customerFinancial()
    {
        return $this->hasOne(CustomerFinancial::class, 'customer_id');
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id');
    }

    public function tags()
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_tag_map', 'customer_id', 'tag_id');
    }


}
