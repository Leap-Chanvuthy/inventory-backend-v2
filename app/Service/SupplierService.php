<?php

namespace App\Service;

use App\Helpers\QueryBuilderHelper;
use App\Helpers\FileUploadHelper;
use App\Helpers\ResponseHelper;
use App\Models\Supplier;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    private function supplierBuilder(Request $request)
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


    public function getAllSuppliers(Request $request)
    {
        try {
            $user = $this->supplierBuilder($request);
            return ResponseHelper::success($user, "Suppliers retrieved successfully", 200);
        }catch (Exception $e) {
            return ResponseHelper::error('Error fetching suppliers', 500, $e->getMessage());
        }
    }

    public function getSupplierById($id)
    {
        try {
            $supplier = Supplier::with('banks')->find($id);
            if (!$supplier) {
                return ResponseHelper::error("Supplier not found", 404, null);
            }
            return ResponseHelper::success($supplier, "Supplier retrieved successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error("Failed getting supplier", 500, $e->getMessage());
        }
    }


}