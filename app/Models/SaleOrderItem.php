<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrderItem extends Model
{
    use HasFactory;

    protected $appends = [
        'allocation_summary',
    ];

    protected $casts = [
        'id' => 'integer',
        'sale_order_id' => 'integer',
        'product_id' => 'integer',
        'sale_movement_id' => 'integer',
        'quantity' => 'float',
        'returned_quantity' => 'float',
        'refund_quantity' => 'float',
        'unit_price_in_usd' => 'float',
        'unit_price_in_riel' => 'float',
        'total_price_in_usd' => 'float',
        'total_price_in_riel' => 'float',
        'exchange_rate_from_usd_to_riel' => 'float',
        'exchange_rate_from_riel_to_usd' => 'float',
    ];

    protected $fillable = [
        'sale_order_id',
        'product_id',
        'sale_movement_id',
        'quantity',
        'returned_quantity',
        'refund_quantity',
        'unit_price_in_usd',
        'unit_price_in_riel',
        'total_price_in_usd',
        'total_price_in_riel',
        'exchange_rate_from_usd_to_riel',
        'exchange_rate_from_riel_to_usd',
        'note',
    ];
    
    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function saleMovement()
    {
        return $this->belongsTo(ProductMovement::class, 'sale_movement_id');
    }

    public function refundItems()
    {
        return $this->hasMany(SaleOrderRefundItem::class, 'sale_order_item_id');
    }

    public function getAllocationSummaryAttribute(): array|null
    {
        if (!$this->saleMovement) {
            return null;
        }

        $saleMovement = $this->saleMovement->loadMissing(['saleAllocations.sourceMovement']);
        $allocations = $saleMovement->saleAllocations ?? collect();

        $totalUsd = $allocations->sum(fn ($row) => (float) $row->allocated_quantity * (float) $row->selling_unit_price_in_usd);
        $totalRiel = $allocations->sum(fn ($row) => (float) $row->allocated_quantity * (float) $row->selling_unit_price_in_riel);
        $quantity = (float) $this->quantity;

        return [
            'sale_method' => $this->product?->sale_method instanceof \BackedEnum
                ? $this->product?->sale_method?->value
                : (string) ($this->product?->sale_method ?? ''),
            'total_quantity' => $quantity,
            'total_amount_usd' => round((float) $totalUsd, 4),
            'total_amount_riel' => round((float) $totalRiel, 4),
            'average_unit_price_usd' => $quantity > 0 ? round((float) $totalUsd / $quantity, 4) : 0,
            'average_unit_price_riel' => $quantity > 0 ? round((float) $totalRiel / $quantity, 4) : 0,
            'lots' => $allocations->map(function ($allocation) {
                $source = $allocation->sourceMovement;
                $lineTotalUsd = (float) $allocation->allocated_quantity * (float) $allocation->selling_unit_price_in_usd;
                $lineTotalRiel = (float) $allocation->allocated_quantity * (float) $allocation->selling_unit_price_in_riel;

                return [
                    'source_movement_id' => (int) $allocation->source_movement_id,
                    'movement_type' => $source && $source->movement_type instanceof \BackedEnum
                        ? $source->movement_type->value
                        : ($source?->movement_type ?? null),
                    'movement_date' => $source?->movement_date?->toDateTimeString(),
                    'allocated_quantity' => (float) $allocation->allocated_quantity,
                    'selling_unit_price_in_usd' => (float) $allocation->selling_unit_price_in_usd,
                    'selling_unit_price_in_riel' => (float) $allocation->selling_unit_price_in_riel,
                    'line_total_usd' => round($lineTotalUsd, 4),
                    'line_total_riel' => round($lineTotalRiel, 4),
                ];
            })->values()->all(),
        ];
    }

}
