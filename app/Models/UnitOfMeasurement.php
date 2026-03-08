<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UnitOfMeasurement (UOM)
 *
 * Hierarchical unit of measurement model that stores a direct conversion_factor
 * to the base unit of its category, enabling O(1) cross-unit conversions.
 *
 * Conversion formula (handled by UomConversionService):
 *   base_qty      = quantity × from_uom.conversion_factor
 *   converted_qty = base_qty  ÷ to_uom.conversion_factor
 *
 * Example (Quantity category):
 *   Very Extra Small Pack → is_base_unit=true,  conversion_factor=1
 *   Extra Small Pack      → is_base_unit=false, conversion_factor=10
 *   Small Pack            → is_base_unit=false, conversion_factor=100
 *   Big Pack              → is_base_unit=false, conversion_factor=1000
 *
 * @property int         $id
 * @property string      $uom_code
 * @property string      $name
 * @property string|null $symbol
 * @property int         $category_id
 * @property int|null    $base_uom_id
 * @property float       $conversion_factor
 * @property bool        $is_base_unit
 * @property bool        $is_active
 * @property string|null $description
 */
class UnitOfMeasurement extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'unit_of_measurements';

    protected $fillable = [
        'uom_code',
        'name',
        'symbol',
        'category_id',
        'base_uom_id',
        'conversion_factor',
        'is_base_unit',
        'is_active',
        'description',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
        'is_base_unit'      => 'boolean',
        'is_active'         => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Query Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: only units whose parent category is NOT soft-deleted.
     *
     * Pair with the SoftDeletes global scope (which already excludes
     * soft-deleted UOM rows).  Together they guarantee:
     *   1. The UOM itself is not deleted.
     *   2. The UOM's category is not deleted.
     *
     * Usage:  UnitOfMeasurement::available()->get()
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereHas(
            'category',
            fn (Builder $q) => $q->whereNull('deleted_at')
        );
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The category this unit belongs to (e.g. Quantity, Weight, Volume).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(UomCategory::class, 'category_id');
    }

    /**
     * The base unit of this category that this unit converts TO.
     * NULL when this unit IS the base unit.
     */
    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasurement::class, 'base_uom_id');
    }

    /**
     * All non-base units that reference this unit as their base.
     */
    public function children(): HasMany
    {
        return $this->hasMany(UnitOfMeasurement::class, 'base_uom_id');
    }

    // -------------------------------------------------------------------------
    // Product & raw material usage relationships
    // -------------------------------------------------------------------------

    /** Products using this as their stock-tracking base unit. */
    public function productsAsBase(): HasMany
    {
        return $this->hasMany(Product::class, 'base_uom_id');
    }

    /** Products using this as their sales display unit. */
    public function productsAsSale(): HasMany
    {
        return $this->hasMany(Product::class, 'sale_uom_id');
    }

    /** Products using this as their purchase unit. */
    public function productsAsPurchase(): HasMany
    {
        return $this->hasMany(Product::class, 'purchase_uom_id');
    }

    /** Raw materials using this as their stock-tracking base unit. */
    public function rawMaterialsAsBase(): HasMany
    {
        return $this->hasMany(RawMaterial::class, 'base_uom_id');
    }

    /** Raw materials using this as their purchase unit. */
    public function rawMaterialsAsPurchase(): HasMany
    {
        return $this->hasMany(RawMaterial::class, 'purchase_uom_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Whether this unit is in the same category as another unit.
     */
    public function isSameCategoryAs(self $other): bool
    {
        return $this->category_id === $other->category_id;
    }
}
