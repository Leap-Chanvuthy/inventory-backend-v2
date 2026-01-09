<?php

namespace App\Validations;


use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use App\Enums\SupplierCategoryEnum;
use App\Helpers\GenerateUniqeCode;
use App\Models\Supplier;
use Illuminate\Validation\Rule;

class SupplierValidation
{
    public function validationFields(Request $request): array
    {
        if (!$request->filled('supplier_code')) {
            $request->merge([
                'supplier_code' => GenerateUniqeCode::generate(
                    modelClass: Supplier::class,
                    field: 'supplier_code',
                    length: 8,
                    prefix: 'SUP'
                ),
            ]);
        }

        return $request->validate([
            'official_name' => 'required|string|max:255',
            'supplier_code' => [
                'required',
                'string',
                'max:12',
                'starts_with:SUP',
                Rule::unique('suppliers', 'supplier_code'),
            ],
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',

            // business info
            'legal_business_name' => 'nullable|string|max:255',
            'tax_identification_number' => 'nullable|string|max:100',
            'business_registration_number' => 'nullable|string|max:100',
            'supplier_category' => [
                'required',
                Rule::in(array_column(SupplierCategoryEnum::cases(), 'value')),
            ],
            'business_description' => 'nullable|string',

            // address
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'village' => 'required|string|max:100',
            'commune' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',

            // only validate existence of banks
            'banks' => 'nullable|array|max:4',
        ]);
    }

    public function updateValidationFields(Request $request, int $id): array
    {
        return $request->validate([
            'official_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',

            'legal_business_name' => 'nullable|string|max:255',
            'tax_identification_number' => 'nullable|string|max:100',
            'business_registration_number' => 'nullable|string|max:100',
            'supplier_category' => [
                'required',
                Rule::in(array_column(SupplierCategoryEnum::cases(), 'value')),
            ],
            'business_description' => 'nullable|string',

            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'village' => 'required|string|max:100',
            'commune' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',

            'banks' => 'nullable|array|max:4',
        ]);
    }
}
