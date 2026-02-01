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
        'quantity' => 'float',
    ];

    protected $fillable = [
        'raw_material_id',
        'quantity',
        'direction',
        'movement_type',
        'movement_date',
    ];

    public function raw_material()
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

}
