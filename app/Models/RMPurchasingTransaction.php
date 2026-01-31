<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RMPurchasingTransaction extends Model
{
    use HasFactory;

    protected $table = 'rm_purchasing_transactions';

    protected $fillable = [
        'raw_material_id',
        'unit_price_in_usd',
        'total_value_in_usd',
        'exchange_rate_from_usd_to_riel',
        'unit_price_in_riel',
        'total_value_in_riel',
        'exchange_rate_from_riel_to_usd',
    ];

    public function raw_material()
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }
}
