<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;

        protected $fillable = [
        // general information
        'image',
        'official_name',
        'supplier_code',
        'contact_person',
        'phone',
        'email',

        // business & legal information
        'legal_business_name',
        'tax_identification_number',
        'business_registration_number',
        'supplier_category',
        'business_description',

        // geolocational information
        'address_line1',
        'address_line2',
        'village',
        'commune',
        'district',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
    ];

    public function banks()
    {
        return $this->hasMany(SupplierBank::class);
    }
}
