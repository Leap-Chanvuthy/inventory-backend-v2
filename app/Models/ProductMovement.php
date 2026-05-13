<?php

namespace App\Models;

use App\Enums\ProductStatusEnum;
use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMovement extends Model
{
    use HasFactory;

    protected $casts = [
        'product_status'                       => ProductStatusEnum::class,
        'direction'                            => StockDirectionEnum::class,
        'movement_type'                        => ProductStockMovementTypeEnum::class,
        'is_sold'                              => 'boolean',
        'movement_date'                        => 'datetime',
        'quantity'                             => 'float',
        'remaining_quantity'                   => 'float',

        // purchasing price
        'purchase_unit_price_in_usd'           => 'float',
        'purchase_total_price_in_usd'          => 'float',
        'purchase_unit_price_in_riel'          => 'float',
        'purchase_total_price_in_riel'         => 'float',
        'exchange_rate_from_usd_to_riel'       => 'float',
        'exchange_rate_from_riel_to_usd'       => 'float',

        // selling price (unit only — no total selling value)
        'selling_unit_price_in_usd'            => 'float',
        'selling_unit_price_in_riel'           => 'float',
        'selling_exchange_rate_from_usd_to_riel' => 'float',
        'selling_exchange_rate_from_riel_to_usd' => 'float',
    ];

    protected $fillable = [
        'product_id',
        'quantity',
        'product_status',
        'direction',
        'movement_type',
        'is_sold',
        'remaining_quantity',
        'movement_date',
        'note',
        'created_by',
        'last_updated_by',

        // purchasing price
        'purchase_unit_price_in_usd',
        'purchase_total_price_in_usd',
        'purchase_unit_price_in_riel',
        'purchase_total_price_in_riel',
        'exchange_rate_from_usd_to_riel',
        'exchange_rate_from_riel_to_usd',

        // selling price (unit only — no total selling value)
        'selling_unit_price_in_usd',
        'selling_unit_price_in_riel',
        'selling_exchange_rate_from_usd_to_riel',
        'selling_exchange_rate_from_riel_to_usd',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function saleAllocations()
    {
        return $this->hasMany(ProductMovementAllocation::class, 'sale_movement_id');
    }

    public function sourceAllocations()
    {
        return $this->hasMany(ProductMovementAllocation::class, 'source_movement_id');
    }

    public function isStockIn(): bool
    {
        $direction = $this->direction instanceof BackedEnum ? $this->direction->value : $this->direction;
        return $direction === StockDirectionEnum::IN->value;
    }

    public function isStockOut(): bool
    {
        $direction = $this->direction instanceof BackedEnum ? $this->direction->value : $this->direction;
        return $direction === StockDirectionEnum::OUT->value;
    }

    public function hasBeenAllocated(): bool
    {
        return $this->sourceAllocations()->exists();
    }

    public function getAllocatedQuantityAttribute(): float
    {
        return (float) $this->sourceAllocations()->sum('allocated_quantity');
    }

    public function getIsFullyConsumedAttribute(): bool
    {
        return (float) $this->remaining_quantity <= 0;
    }
}
