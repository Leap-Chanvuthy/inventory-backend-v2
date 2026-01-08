<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierBank extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'bank_name',
        'account_number',  
        'account_holder_name',
        'payment_link',
        'qr_code_image',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    
}
