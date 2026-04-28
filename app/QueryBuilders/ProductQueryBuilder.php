<?php

namespace App\QueryBuilders;

use App\Helpers\QueryBuilderHelper;
use App\Models\Product;
use App\Models\ProductMovement;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductQueryBuilder
{
    public function productBuilder(Request $request, bool $onlyTrashed = false)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $latestSellingUsd = "(select pm.selling_unit_price_in_usd from product_movements pm where pm.product_id = products.id and pm.direction = 'IN' and (pm.selling_unit_price_in_usd > 0 or pm.selling_unit_price_in_riel > 0) order by pm.movement_date desc, pm.id desc limit 1)";
        $latestSellingRiel = "(select pm.selling_unit_price_in_riel from product_movements pm where pm.product_id = products.id and pm.direction = 'IN' and (pm.selling_unit_price_in_usd > 0 or pm.selling_unit_price_in_riel > 0) order by pm.movement_date desc, pm.id desc limit 1)";
        $latestSellingUsdToRiel = "(select pm.selling_exchange_rate_from_usd_to_riel from product_movements pm where pm.product_id = products.id and pm.direction = 'IN' and (pm.selling_unit_price_in_usd > 0 or pm.selling_unit_price_in_riel > 0) order by pm.movement_date desc, pm.id desc limit 1)";
        $latestSellingRielToUsd = "(select pm.selling_exchange_rate_from_riel_to_usd from product_movements pm where pm.product_id = products.id and pm.direction = 'IN' and (pm.selling_unit_price_in_usd > 0 or pm.selling_unit_price_in_riel > 0) order by pm.movement_date desc, pm.id desc limit 1)";

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
                DB::raw("{$latestSellingUsd} as latest_selling_unit_price_in_usd"),
                DB::raw("{$latestSellingRiel} as latest_selling_unit_price_in_riel"),
                DB::raw("{$latestSellingUsdToRiel} as latest_selling_exchange_rate_from_usd_to_riel"),
                DB::raw("{$latestSellingRielToUsd} as latest_selling_exchange_rate_from_riel_to_usd"),
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

    public function productMovementBuilder(Request $request, int $productId)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $builder = QueryBuilderHelper::build(
            model: ProductMovement::class,

            joins: [
                ['products',               'product_movements.product_id', '=', 'products.id'],
                ['unit_of_measurements',   'products.base_uom_id',        '=', 'unit_of_measurements.id'],
                ['users as created_by_user','product_movements.created_by',      '=', 'created_by_user.id'],
                ['users as last_updated_by_user','product_movements.last_updated_by', '=', 'last_updated_by_user.id'],
            ],

            selects: [
                'product_movements.*',
                'unit_of_measurements.name as uom_name',
                'unit_of_measurements.symbol as uom_symbol',
                'created_by_user.name as created_by_name',
                'created_by_user.email as created_by_email',
                'last_updated_by_user.name as last_updated_by_name',
                'last_updated_by_user.email as last_updated_by_email',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('movement_type'),
                AllowedFilter::exact('direction'),
            ],

            allowedSorts: [
                'movement_date',
                'created_at',
                'updated_at',
                'quantity',
            ],

            defaultSort: '-movement_date',

            withRelations: [
                'createdBy',
                'lastUpdatedBy',
            ],

            withCounts: [],
        );

        return $builder
            ->where('product_movements.product_id', $productId)
            ->paginate($perPage)
            ->appends($request->query());
    }

}
