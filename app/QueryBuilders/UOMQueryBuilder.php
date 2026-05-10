<?php

namespace App\QueryBuilders;

use App\Helpers\QueryBuilderHelper;
use App\Models\UnitOfMeasurement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;

class UOMQueryBuilder
{
    public function UOMBuilder(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return QueryBuilderHelper::build(
            model: UnitOfMeasurement::class,

            joins: [
                ['uom_categories', 'uom_categories.id', '=', 'unit_of_measurements.category_id'],
            ],

            selects: [
                'unit_of_measurements.id',
                'unit_of_measurements.uom_code',
                'unit_of_measurements.name',
                'unit_of_measurements.symbol',
                'unit_of_measurements.category_id',
                'uom_categories.name as category_name',
                'unit_of_measurements.base_uom_id',
                'unit_of_measurements.conversion_factor',
                'unit_of_measurements.is_base_unit',
                'unit_of_measurements.is_active',
                'unit_of_measurements.description',
                'unit_of_measurements.created_at',
                'unit_of_measurements.updated_at',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('is_base_unit'),
                AllowedFilter::exact('category_id'),

                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('unit_of_measurements.name',     'LIKE', "%{$value}%")
                          ->orWhere('unit_of_measurements.symbol',   'LIKE', "%{$value}%")
                          ->orWhere('unit_of_measurements.uom_code', 'LIKE', "%{$value}%")
                          ->orWhere('uom_categories.name',           'LIKE', "%{$value}%");
                    });
                }),
            ],

            allowedSorts: [
                'uom_code',
                'name',
                'symbol',
                'conversion_factor',
                'is_base_unit',
                'created_at',
                'updated_at',
            ],

            defaultSort: '-created_at'
        )
            // Only return units whose parent category has NOT been soft-deleted.
            // The LEFT JOIN on uom_categories is already applied above, so we
            // can reference the joined column directly — no sub-query needed.
            ->whereNull('uom_categories.deleted_at')
            ->paginate($perPage)
            ->appends($request->query());
    }
}
