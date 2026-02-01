<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RMPurchasingTransaction extends Model
{
    use HasFactory;

    protected $table = 'rm_purchasing_transactions';

    protected $casts = [
        'raw_material_id' => 'integer',
        'supplier_id' => 'integer',
        'quantity' => 'float',
        'unit_price_in_usd' => 'float',
        'total_value_in_usd' => 'float',
        'exchange_rate_from_usd_to_riel' => 'float',
        'unit_price_in_riel' => 'float',
        'total_value_in_riel' => 'float',
        'exchange_rate_from_riel_to_usd' => 'float',
    ];

    protected $fillable = [
        'raw_material_id',
        'supplier_id',
        'unit_price_in_usd',
        'total_value_in_usd',
        'exchange_rate_from_usd_to_riel',
        'unit_price_in_riel',
        'total_value_in_riel',
        'exchange_rate_from_riel_to_usd',
        'quantity',
        'transaction_date',
    ];

    public function raw_material()
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
