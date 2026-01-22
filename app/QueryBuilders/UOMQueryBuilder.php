<?php

namespace App\QueryBuilders;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Helpers\QueryBuilderHelper;
use App\Models\UOM;
use Illuminate\Http\Request;

class UOMQueryBuilder
{
    // UOM Query Builder code here
    public function UOMBuilder(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return QueryBuilderHelper::build(
            model: UOM::class,

            joins: [],
            selects: [
                'unit_of_measurements.id',
                'unit_of_measurements.uom_code',
                'unit_of_measurements.name',
                'unit_of_measurements.symbol',
                'unit_of_measurements.uom_type',
                'unit_of_measurements.description',
                'unit_of_measurements.is_active',
                'unit_of_measurements.created_at',
                'unit_of_measurements.updated_at',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('is_active'),

                // Search by name / email / phone_number
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('unit_of_measurements.name', 'LIKE', "%{$value}%")
                            ->orWhere('unit_of_measurements.symbol', 'LIKE', "%{$value}%")
                            ->orWhere('unit_of_measurements.uom_type', 'LIKE', "%{$value}%")
                            ->orWhere('unit_of_measurements.uom_code', 'LIKE', "%{$value}%");
                    });
                }),
            ],

            allowedSorts: [
                'uom_code',
                'name',
                'symbol',
                'uom_type',
                'created_at',
                'updated_at',
            ],

            defaultSort: '-created_at'
        )
            ->paginate($perPage)
            ->appends($request->query());;
    }
}