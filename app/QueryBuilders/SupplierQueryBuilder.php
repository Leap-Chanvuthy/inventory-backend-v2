<?php

namespace App\QueryBuilders;

use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Illuminate\Database\Eloquent\Builder;
use App\Helpers\QueryBuilderHelper;
use App\Models\RMStockMovement;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierQueryBuilder
{
    public function supplierBuilder(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return QueryBuilderHelper::build(
            model: Supplier::class,

            joins: [],
            selects: [
                'suppliers.id',
                'suppliers.official_name',
                'suppliers.supplier_code',
                'suppliers.contact_person',
                'suppliers.phone',
                'suppliers.email',
                'suppliers.image',
                
                // legal and business information
                'suppliers.legal_business_name',
                'suppliers.tax_identification_number',
                'suppliers.business_registration_number',
                'suppliers.supplier_category',
                'suppliers.business_description',

                // geolocational information
                'suppliers.address_line1',
                'suppliers.address_line2',
                'suppliers.village',
                'suppliers.commune',
                'suppliers.district',
                'suppliers.city',
                'suppliers.province',
                'suppliers.postal_code',
                'suppliers.latitude',
                'suppliers.longitude',
                
                'suppliers.created_at',
                'suppliers.updated_at',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('supplier_category'),
                


                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('suppliers.official_name', 'LIKE', "%{$value}%")
                            ->orWhere('suppliers.email', 'LIKE', "%{$value}%")
                            ->orWhere('suppliers.supplier_code', 'LIKE', "%{$value}%")
                            ->orWhere('suppliers.tax_identification_number', 'LIKE', "%{$value}%")
                            ->orWhere('suppliers.phone', 'LIKE', "%{$value}%");
                    });
                }),
            ],

            allowedSorts: [
                'created_at',
                'updated_at',
                'supplier_category',
                'supplier_code',
            ],

            defaultSort: '-created_at',
            withRelations: ['banks'],
            withCounts: ['banks'],
        )
        ->paginate($perPage)
        ->appends($request->query());
        ;
    }


        public function supplierTransactionsBuilder(Request $request , int $supplierId)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));
            $movementTypes = [
                \App\Enums\RawMaterialStockMovementTypeEnum::PURCHASE->value,
                \App\Enums\RawMaterialStockMovementTypeEnum::RE_ORDER->value,
            ];

            $joins = [
                ['raw_materials', 'raw_materials.id', '=', 'rm_stock_movements.raw_material_id'],
            ];

            $selects = [
                'rm_stock_movements.id',
                'rm_stock_movements.raw_material_id',
                'raw_materials.material_name as raw_material_name',
                'raw_materials.material_sku_code as raw_material_sku_code',
                'rm_stock_movements.quantity',
                'rm_stock_movements.unit_price_in_usd',
                'rm_stock_movements.total_value_in_usd',
                'rm_stock_movements.movement_type',
                'rm_stock_movements.direction',
                'rm_stock_movements.movement_date',
                'rm_stock_movements.note',
                'rm_stock_movements.created_at',
                'rm_stock_movements.updated_at',
            ];

            $allowedFilters = [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('movement_type'),
                AllowedFilter::exact('direction'),
                AllowedFilter::exact('raw_material_id'),
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('raw_materials.material_name', 'LIKE', "%{$value}%")
                            ->orWhere('raw_materials.material_sku_code', 'LIKE', "%{$value}%");
                    });
                }),
            ];

            $allowedSorts = [
                AllowedSort::field('created_at', 'rm_stock_movements.created_at'),
                AllowedSort::field('updated_at', 'rm_stock_movements.updated_at'),
                AllowedSort::field('direction', 'rm_stock_movements.direction'),
                AllowedSort::field('movement_type', 'rm_stock_movements.movement_type'),
                AllowedSort::field('movement_date', 'rm_stock_movements.movement_date'),
            ];

            $builder = QueryBuilderHelper::build(
                model: RMStockMovement::class,
                joins: $joins,
                selects: $selects,
                allowedFilters: $allowedFilters,
                allowedSorts: $allowedSorts,
                defaultSort: '-rm_stock_movements.created_at',
                withRelations: ['raw_material'],
                withCounts: []
            );

            // Restrict to supplier
            $builder->where('raw_materials.supplier_id', $supplierId);

            // Restrict to purchase & reorder movements
            $builder->whereIn('rm_stock_movements.movement_type', $movementTypes);

            return $builder->paginate($perPage)
                ->appends($request->query());
    }

}