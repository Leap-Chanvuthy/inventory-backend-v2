<?php

namespace App\QueryBuilders;

use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Helpers\QueryBuilderHelper;
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
                AllowedFilter::exact('role'),

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
}