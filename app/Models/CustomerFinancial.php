<?php

namespace App\Models;

use App\Enums\PaymentTermEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerFinancial extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'payment_terms',
    ];

    protected $casts = [
        'payment_terms' => PaymentTermEnum::class,
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
