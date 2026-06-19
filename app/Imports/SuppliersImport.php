<?php

namespace App\Imports;

use App\Enums\SupplierCategoryEnum;
use App\Models\Supplier;
use App\Helpers\GenerateUniqeCode;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
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

    public function prepareForValidation(array $row, int $index): array
    {
        foreach ($this->stringColumns() as $column) {
            if (!array_key_exists($column, $row) || $row[$column] === null) {
                continue;
            }

            $value = trim((string) $row[$column]);
            $row[$column] = $value !== '' ? $value : null;
        }

        return $row;
    }

    public function model(array $row)
    {
        // Only called for VALID rows
        $this->imported++;

        $supplierCode = $row['supplier_code'] ?? null;

        return new Supplier([
            'official_name' => $row['official_name'],

            // if supplier_code column is missing/empty, generate one
            'supplier_code' => !empty($supplierCode)
                ? $supplierCode
                : GenerateUniqeCode::generate(Supplier::class, 'supplier_code', 8, 'SUP'),

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

            // ✅ optional; validate only if provided
            'supplier_code' => [
                'nullable',
                'string',
                'max:12',
                'starts_with:SUP',
                Rule::unique('suppliers', 'supplier_code'),
            ],

            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',

            'legal_business_name' => 'nullable|string|max:255',
            'tax_identification_number' => 'nullable|string|max:100',
            'business_registration_number' => 'nullable|string|max:100',

            // ✅ validate enum properly
            'supplier_category' => ['required', new Enum(SupplierCategoryEnum::class)],

            'business_description' => 'nullable|string',

            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'village' => 'required|string',
            'commune' => 'required|string',
            'district' => 'required|string',
            'city' => 'required|string',
            'province' => 'required|string',
            'postal_code' => 'nullable|string|max:20',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    public function getImportedCount(): int
    {
        return $this->imported;
    }

    private function stringColumns(): array
    {
        return [
            'supplier_code',
            'official_name',
            'contact_person',
            'phone',
            'email',
            'legal_business_name',
            'tax_identification_number',
            'business_registration_number',
            'supplier_category',
            'business_description',
            'address_line1',
            'address_line2',
            'village',
            'commune',
            'district',
            'city',
            'province',
            'postal_code',
        ];
    }
}
