<?php

namespace App\Validations;

use App\Enums\UomQuantityTypeEnum;
use App\Helpers\GenerateUniqeCode;
use App\Models\UnitOfMeasurement;
use App\Models\UomCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UOMValidation
{
    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    /**
     * Validate and return clean data for creating a new UOM.
     *
     * Rules enforced:
     *  1. Category must exist.
     *  2. Only one base unit may exist per category.
     *  3. conversion_factor must be > 0.
     *  4. Non-base units must reference a valid base_uom_id.
     *  5. base_uom_id must belong to the same category.
     *  6. Name must be unique within the category.
     *
     * @throws ValidationException
     */
    public function CreateValidationFields(Request $request): array
    {
        // Auto-generate uom_code if not supplied
        if (! $request->filled('uom_code')) {
            $request->merge([
                'uom_code' => GenerateUniqeCode::generate(
                    UnitOfMeasurement::class,
                    'uom_code',
                    8,
                    'UOM'
                ),
            ]);
        }

        $isBaseUnit = filter_var($request->input('is_base_unit', false), FILTER_VALIDATE_BOOLEAN);

        $validator = Validator::make($request->all(), [
            'uom_code' => [
                'required',
                'string',
                'max:20',
                'starts_with:UOM',
                Rule::unique('unit_of_measurements', 'uom_code'),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                // Name unique within the same category
                Rule::unique('unit_of_measurements', 'name')
                    ->where('category_id', $request->input('category_id')),
            ],
            'symbol'            => 'nullable|string|max:20',
            'category_id'       => ['required', 'integer', Rule::exists('uom_categories', 'id')],
            'is_base_unit'      => 'boolean',
            'conversion_factor' => 'nullable|numeric|min:0.000001',
            'base_uom_id'       => [
                $isBaseUnit ? 'nullable' : 'required',
                'nullable',
                'integer',
                Rule::exists('unit_of_measurements', 'id'),
            ],
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ], [
            'uom_code.required'         => 'A unit code is required.',
            'uom_code.max'              => 'Unit code must not exceed 20 characters.',
            'uom_code.starts_with'      => 'Unit code must start with "UOM".',
            'uom_code.unique'           => 'This unit code is already in use. Please try again.',
            'name.required'             => 'Please enter a unit name.',
            'name.max'                  => 'Unit name must not exceed 100 characters.',
            'name.unique'               => 'This unit name is already used in this category. Please choose a different name.',
            'symbol.max'                => 'Symbol must not exceed 20 characters.',
            'category_id.required'      => 'Please select a category for this unit.',
            'category_id.integer'       => 'Invalid category selection.',
            'category_id.exists'        => 'The selected category does not exist. Please refresh and try again.',
            'conversion_factor.numeric' => 'Please enter a valid number for the conversion amount.',
            'conversion_factor.min'     => 'The conversion amount must be greater than zero.',
            'base_uom_id.required'      => 'Please select a parent unit for this derived unit.',
            'base_uom_id.integer'       => 'Invalid parent unit selection.',
            'base_uom_id.exists'        => 'The selected parent unit no longer exists. Please refresh and try again.',
            'description.max'           => 'Description must not exceed 500 characters.',
        ]);

        $validated = $validator->validate();

        // --- Business rule: only one base unit per category ---
        if ($isBaseUnit) {
            $this->assertNoDuplicateBaseUnit($validated['category_id']);
            $validated['conversion_factor'] = 1.000000;
            $validated['base_uom_id']       = null;
        } else {
            // Ensure the referenced base_uom belongs to the same category
            if (! empty($validated['base_uom_id'])) {
                $this->assertBaseUomCategory(
                    (int) $validated['base_uom_id'],
                    (int) $validated['category_id']
                );
            }
        }

        $this->assertIntegerConversionFactorForIntegerCategory(
            (int) $validated['category_id'],
            $validated['conversion_factor'] ?? null
        );

        return $validated;
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    /**
     * Validate and return clean data for updating an existing UOM.
     *
     * @throws ValidationException
     */
    public function UpdateValidationFields(Request $request, ?int $id = null): array
    {
        $current    = UnitOfMeasurement::findOrFail($id);
        $isBaseUnit = filter_var(
            $request->input('is_base_unit', $current->is_base_unit),
            FILTER_VALIDATE_BOOLEAN
        );

        $categoryId = $request->input('category_id', $current->category_id);

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('unit_of_measurements', 'name')
                    ->where('category_id', $categoryId)
                    ->ignore($id),
            ],
            'symbol'            => 'nullable|string|max:20',
            'category_id'       => ['sometimes', 'integer', Rule::exists('uom_categories', 'id')],
            'is_base_unit'      => 'boolean',
            'conversion_factor' => 'nullable|numeric|min:0.000001',
            'base_uom_id'       => [
                'nullable',
                'integer',
                Rule::exists('unit_of_measurements', 'id'),
            ],
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ], [
            'name.required'             => 'Please enter a unit name.',
            'name.max'                  => 'Unit name must not exceed 100 characters.',
            'name.unique'               => 'This unit name is already used in this category. Please choose a different name.',
            'symbol.max'                => 'Symbol must not exceed 20 characters.',
            'category_id.integer'       => 'Invalid category selection.',
            'category_id.exists'        => 'The selected category does not exist. Please refresh and try again.',
            'conversion_factor.numeric' => 'Please enter a valid number for the conversion amount.',
            'conversion_factor.min'     => 'The conversion amount must be greater than zero.',
            'base_uom_id.integer'       => 'Invalid parent unit selection.',
            'base_uom_id.exists'        => 'The selected parent unit no longer exists. Please refresh and try again.',
            'description.max'           => 'Description must not exceed 500 characters.',
        ]);

        $validated = $validator->validate();

        if ($isBaseUnit) {
            // Allow the current record to stay as base; only block a different base
            $this->assertNoDuplicateBaseUnit((int) $categoryId, $id);
            $validated['conversion_factor'] = 1.000000;
            $validated['base_uom_id']       = null;
        } else {
            if (! empty($validated['base_uom_id'])) {
                $this->assertBaseUomCategory(
                    (int) $validated['base_uom_id'],
                    (int) $categoryId
                );
            }
        }

        $conversionFactor = $validated['conversion_factor'] ?? $current->conversion_factor;
        $this->assertIntegerConversionFactorForIntegerCategory(
            (int) $categoryId,
            $conversionFactor
        );

        return $validated;
    }

    // -------------------------------------------------------------------------
    // Private guards
    // -------------------------------------------------------------------------

    /**
     * Throw a ValidationException if the category already has a base unit
     * (optionally ignoring the provided $exceptId, used during updates).
     *
     * @throws ValidationException
     */
    private function assertNoDuplicateBaseUnit(int $categoryId, ?int $exceptId = null): void
    {
        $query = UnitOfMeasurement::where('category_id', $categoryId)
                                  ->where('is_base_unit', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            $category = UomCategory::find($categoryId);
            throw ValidationException::withMessages([
                'is_base_unit' => [
                    "Category '{$category?->name}' already has a base unit. " .
                    'Each category must have exactly one base unit.',
                ],
            ]);
        }
    }

    /**
     * Throw a ValidationException if the referenced base UOM does not belong
     * to the expected category.
     *
     * @throws ValidationException
     */
    private function assertBaseUomCategory(int $baseUomId, int $expectedCategoryId): void
    {
        $baseUom = UnitOfMeasurement::find($baseUomId);

        if (! $baseUom || (int) $baseUom->category_id !== $expectedCategoryId) {
            throw ValidationException::withMessages([
                'base_uom_id' => [
                    "The base_uom_id must reference a UOM that belongs to the same category.",
                ],
            ]);
        }

        if (! $baseUom->is_base_unit) {
            throw ValidationException::withMessages([
                'base_uom_id' => [
                    "The referenced UOM '{$baseUom->name}' is not a base unit. " .
                    'All non-base units must reference the category base unit directly.',
                ],
            ]);
        }
    }

    /**
     * Enforce integer conversion_factor when category.quantity_type is INTEGER.
     *
     * @throws ValidationException
     */
    private function assertIntegerConversionFactorForIntegerCategory(int $categoryId, mixed $conversionFactor): void
    {
        $category = UomCategory::find($categoryId);
        if (! $category) {
            return;
        }

        $quantityType = $category->quantity_type instanceof UomQuantityTypeEnum
            ? $category->quantity_type->value
            : (string) ($category->quantity_type ?? UomQuantityTypeEnum::DECIMAL->value);

        if ($quantityType !== UomQuantityTypeEnum::INTEGER->value) {
            return;
        }

        if ($conversionFactor === null || $this->isWholeNumber($conversionFactor)) {
            return;
        }

        throw ValidationException::withMessages([
            'conversion_factor' => [
                "This category only allows whole numbers. Conversion factor must be an integer.",
            ],
        ]);
    }

    private function isWholeNumber(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_float($value)) {
            return abs($value - round($value)) < 1e-9;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return false;
        }

        if (preg_match('/^-?\d+$/', $stringValue) === 1) {
            return true;
        }

        if (preg_match('/^-?\d+\.0+$/', $stringValue) === 1) {
            return true;
        }

        if (! is_numeric($stringValue)) {
            return false;
        }

        $floatValue = (float) $stringValue;
        return abs($floatValue - round($floatValue)) < 1e-9;
    }
}
