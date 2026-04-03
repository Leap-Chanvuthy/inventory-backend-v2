<?php

namespace App\QueryBuilders;

use App\Helpers\QueryBuilderHelper;
use App\Models\Product;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;

class ProductQueryBuilder
{
    public function productBuilder(Request $request, bool $onlyTrashed = false)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $builder = QueryBuilderHelper::build(
            model: Product::class,

            joins: [
                ['product_categories',    'products.product_category_id', '=', 'product_categories.id'],
                ['suppliers',             'products.supplier_id',         '=', 'suppliers.id'],
                ['warehouses',            'products.warehouse_id',        '=', 'warehouses.id'],
                ['unit_of_measurements',  'products.base_uom_id',         '=', 'unit_of_measurements.id'],
            ],

            selects: [
                'products.*',
                'product_categories.category_name as product_category_name',
                'suppliers.official_name as official_name',
                'warehouses.warehouse_name as warehouse_name',
                'unit_of_measurements.name as uom_name',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('product_category_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('base_uom_id'),
                AllowedFilter::partial('product_type'),

                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('products.product_name',          'LIKE', "%{$value}%")
                          ->orWhere('products.product_sku_code',    'LIKE', "%{$value}%")
                          ->orWhere('product_categories.category_name', 'LIKE', "%{$value}%")
                          ->orWhere('suppliers.official_name',      'LIKE', "%{$value}%")
                          ->orWhere('warehouses.warehouse_name',    'LIKE', "%{$value}%")
                          ->orWhere('unit_of_measurements.name',   'LIKE', "%{$value}%");
                    });
                }),

                AllowedFilter::callback('category_name', function (Builder $query, $value) {
                    $query->where('product_categories.category_name', 'LIKE', "%{$value}%");
                }),
            ],

            allowedSorts: [
                'created_at',
                'updated_at',
                'deleted_at',
                'product_name',
                'product_sku_code',
                'product_category_name',
                'official_name',
                'warehouse_name',
                'uom_name',
                'product_category_id',
                'supplier_id',
                'warehouse_id',
                'base_uom_id',
            ],

            defaultSort: '-created_at',

            withRelations: [
                'category'  => fn ($q) => $q->withTrashed(),
                'supplier'  => fn ($q) => $q->withTrashed(),
                'warehouse' => fn ($q) => $q->withTrashed(),
                'baseUom'   => fn ($q) => $q->withTrashed(),
            ],

            withCounts: [],
        );

        if ($onlyTrashed) {
            $builder = $builder->onlyTrashed();
        }

        return $builder
            ->paginate($perPage)
            ->appends($request->query());
    }
}
