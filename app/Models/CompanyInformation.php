<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyInformation extends Model
{
    use HasFactory;

    // ...existing code...

    /**
     * Explicit table name (migration created 'company_information').
     */
    protected $table = 'company_information';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_name',
        'company_logo',
        'email',
        'phone_number',
        'contact_person',
        'industry_type',
        'website_url',
        'date_established',
        'vat_number',
        'description',
        'full_address',
        'house_number',
        'street',
        'commune',
        'district',
        'city',
        'telegram_inventory_chat_id',
        'telegram_sale_chat_id',
        'telegram_purchase_chat_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date_established' => 'date',
    ];

    public function banking_infos(){
        return $this ->hasMany(CompanyBankingInfo::class);
    }

}