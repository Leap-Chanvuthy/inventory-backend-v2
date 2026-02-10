<?php

namespace App\Models;

use App\Enums\RawMaterialStockMovementTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RMStockMovement extends Model
{
    use HasFactory;

    protected $table = 'rm_stock_movements';

    protected $casts = [
        'movement_type' => RawMaterialStockMovementTypeEnum::class,
        'raw_material_id' => 'integer',
        'direction' => 'string',
        'quantity' => 'float',
        'in_used' => 'boolean',
        'unit_price_in_usd' => 'float',
        'total_value_in_usd' => 'float',
        'exchange_rate_from_usd_to_riel' => 'float',
        'unit_price_in_riel' => 'float',
        'total_value_in_riel' => 'float',
        'exchange_rate_from_riel_to_usd' => 'float',
        'movement_date' => 'datetime',
        'note' => 'string',
    ];

    protected $fillable = [
        'raw_material_id',
        'quantity',
        'in_used',
        'direction',
        'movement_type',
        'movement_date',
        'unit_price_in_usd',
        'total_value_in_usd',
        'exchange_rate_from_usd_to_riel',
        'unit_price_in_riel',
        'total_value_in_riel',
        'exchange_rate_from_riel_to_usd',
        'note',
    ];

    public function raw_material()
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

}
