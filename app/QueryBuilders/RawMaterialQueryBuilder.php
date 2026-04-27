<?php

namespace App\QueryBuilders;

use App\Helpers\QueryBuilderHelper;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;

class RawMaterialQueryBuilder {

    public function rawMaterialBuilder(Request $request, bool $onlyTrashed = false)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $stockQtyExpression = "(
            SELECT COALESCE(
                SUM(
                    CASE
                        WHEN rm_stock_movements.direction = 'OUT' THEN -rm_stock_movements.quantity
                        ELSE rm_stock_movements.quantity
                    END
                ),
                0
            )
            FROM rm_stock_movements
            WHERE rm_stock_movements.raw_material_id = raw_materials.id
        )";

        $builder = QueryBuilderHelper::build(
            model: RawMaterial::class,

            joins: [
                // Enable searching/sorting by category name
                ['raw_material_categories', 'raw_materials.raw_material_category_id', '=', 'raw_material_categories.id'],
                ['suppliers', 'raw_materials.supplier_id', '=', 'suppliers.id'],
                ['warehouses', 'raw_materials.warehouse_id', '=', 'warehouses.id'],
                ['unit_of_measurements', 'raw_materials.base_uom_id', '=', 'unit_of_measurements.id'],
            ],

            selects: [
                'raw_materials.*',
                // Useful for UI; remove if you don't need it
                'raw_material_categories.category_name as raw_material_category_name',
                'suppliers.official_name as official_name',
                'warehouses.warehouse_name as warehouse_name',
                'unit_of_measurements.name as uom_name',
                // Current quantity in stock (sum of stock movements: IN as +, OUT as -)
                \DB::raw("{$stockQtyExpression} as current_qty_in_stock"),
                // Explicit field alias requested by frontend/clients.
                \DB::raw("{$stockQtyExpression} as stock_availability"),
                \DB::raw("
                    CASE
                        WHEN {$stockQtyExpression} <= 0 THEN 'OUT_OF_STOCK'
                        WHEN {$stockQtyExpression} <= COALESCE(raw_materials.minimum_stock_level, 0) THEN 'LOW_STOCK'
                        ELSE 'IN_STOCK'
                    END as stock_availability_status
                "),
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('raw_material_category_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('base_uom_id'),


                // Search by fullname / email_address / phone_number / category name
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('raw_materials.material_name', 'LIKE', "%{$value}%")
                          ->orWhere('raw_materials.material_sku_code', 'LIKE', "%{$value}%")
                          ->orWhere('raw_material_categories.category_name', 'LIKE', "%{$value}%")
                          ->orWhere('suppliers.official_name', 'LIKE', "%{$value}%")
                          ->orWhere('warehouses.warehouse_name', 'LIKE', "%{$value}%")
                          ->orWhere('unit_of_measurements.name', 'LIKE', "%{$value}%");
                    });
                }),

                // Optional: dedicated filter e.g. ?filter[category_name]=Retail
                AllowedFilter::callback('category_name', function (Builder $query, $value) {
                    $query->where('raw_material_categories.category_name', 'LIKE', "%{$value}%");
                }),
            ],

            allowedSorts: [
                'created_at',
                'updated_at',
                'deleted_at',
                'material_name',
                'material_sku_code',
                'raw_material_category_name',
                'official_name',
                'warehouse_name',
                'uom_name',
                'status',
                'raw_material_category_id',
                'supplier_id',
                'warehouse_id',
                'base_uom_id',
                'current_qty_in_stock',
                'stock_availability',
            ],

            defaultSort: '-created_at',
            withRelations: [  
                'rm_category' => fn ($q) => $q->withTrashed(),
                'supplier' => fn ($q) => $q->withTrashed(),
                'warehouse' => fn ($q) => $q->withTrashed(),
                'baseUom' => fn ($q) => $q->withTrashed(),

            ],
            
        );

        if ($onlyTrashed) {
            $builder = $builder->onlyTrashed();
        }

        return $builder
            ->paginate($perPage)
            ->appends($request->query());
    }

}
