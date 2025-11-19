<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyBankingInfo extends Model
{
    use HasFactory;

    protected $table = 'company_banking_infos';

    protected $fillable = [
        'company_information_id',
        'bank_name',
        'bank_account_holder_name',
        'bank_account_number',
        'payment_link',
        'khqr_code',
        'payment_method_label',
        'set_as_default',
    ];

    public function company(){
        return $this->belongsTo(CompanyInformation::class , 'company_information_id');
    }

}
