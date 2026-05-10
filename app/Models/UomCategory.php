<?php

namespace App\Models;

use App\Enums\UomQuantityTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UomCategory
 *
 * Groups unit of measurements to prevent invalid cross-category conversions.
 * Examples: Quantity, Weight, Volume, Length, Area.
 *
 * @property int              $id
 * @property string         $name
 * @property string|null    $description
 * @property UomQuantityTypeEnum|string $quantity_type
 * @property \Carbon\Carbon|null $deleted_at
 */
class UomCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'uom_categories';

    protected $fillable = [
        'name',
        'description',
        'quantity_type',
    ];

    protected $casts = [
        'quantity_type' => UomQuantityTypeEnum::class,
    ];

    // -------------------------------------------------------------------------
    // Query Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: only non-deleted categories.
     * Used when you need to be explicit (e.g. inside joins).
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('uom_categories.deleted_at');
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * All unit of measurements that belong to this category.
     * Intentionally does NOT filter by UOM deleted_at —
     * this is used for counts and relationship access.
     */
    public function unitOfMeasurements(): HasMany
    {
        return $this->hasMany(UnitOfMeasurement::class, 'category_id');
    }

    /**
     * Convenience alias.
     */
    public function units(): HasMany
    {
        return $this->unitOfMeasurements();
    }

    /**
     * Returns the single base unit for this category (is_base_unit = true).
     */
    public function baseUnit(): HasOne
    {
        return $this->hasOne(UnitOfMeasurement::class, 'category_id')
                    ->where('is_base_unit', true);
    }
}
