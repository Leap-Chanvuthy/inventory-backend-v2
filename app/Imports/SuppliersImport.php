<?php

namespace App\Imports;

use App\Enums\SupplierCategoryEnum;
use App\Models\Supplier;
use App\Helpers\GenerateUniqeCode;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\{
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure,
    SkipsFailures
};

class SuppliersImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    protected int $imported = 0;

    public function model(array $row)
    {
        $this->imported++;

        return new Supplier([
            'official_name' => $row['official_name'],
            'supplier_code' => GenerateUniqeCode::generate(
                Supplier::class,
                'supplier_code',
                8,
                'SUP'
            ),
            'contact_person' => $row['contact_person'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,

            'legal_business_name' => $row['legal_business_name'] ?? null,
            'tax_identification_number' => $row['tax_identification_number'] ?? null,
            'business_registration_number' => $row['business_registration_number'] ?? null,
            'supplier_category' => $row['supplier_category'],
            'business_description' => $row['business_description'] ?? null,

            'address_line1' => $row['address_line1'],
            'address_line2' => $row['address_line2'] ?? null,
            'village' => $row['village'],
            'commune' => $row['commune'],
            'district' => $row['district'],
            'city' => $row['city'],
            'province' => $row['province'],
            'postal_code' => $row['postal_code'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'official_name' => 'required|string|max:255',
            'supplier_code' => [
                'nullable',
                'string',
                'unique:suppliers,supplier_code',
                'max:12',
                'starts_with:SUP',
                Rule::unique('suppliers', 'supplier_code'),
            ],
            'email' => 'nullable|email|unique:suppliers,email',
            'supplier_category' => [
                'required',
                Rule::in([SupplierCategoryEnum::CLOTHING , SupplierCategoryEnum::ELECTRONICS , SupplierCategoryEnum::FOOD , SupplierCategoryEnum::LOGISTICS , SupplierCategoryEnum::PRODUCTS , SupplierCategoryEnum::SERVICES , SupplierCategoryEnum::OTHERS]),
            ],

            'address_line1' => 'required|string',
            'village' => 'required|string',
            'commune' => 'required|string',
            'district' => 'required|string',
            'city' => 'required|string',
            'province' => 'required|string',
        ];
    }

    public function getImportedCount(): int
    {
        return $this->imported;
    }
}
