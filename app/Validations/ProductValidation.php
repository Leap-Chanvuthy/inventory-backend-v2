<?php

namespace App\Validations;

use App\Helpers\UomQuantityGuard;
use App\Helpers\GenerateUniqueSKU;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductValidation
{
    // ─────────────────────────────────────────────────────────────────────────
    // Product core validation
    // Auto-generates product_sku_code using format: PRD-{CATEGORY}-{RANDOM}
    // ─────────────────────────────────────────────────────────────────────────

    public function createProductRules(Request $request): array
    {
        $product   = new Product();
        $relations = [];
        $format    = '{prefix}-{random}';

        if ($request->filled('product_category_id')) {
            $cat = ProductCategory::find($request->input('product_category_id'));

            if ($cat) {
                $product->category()->associate($cat);
                $relations['cat'] = 'category.category_name';
                $format           = '{prefix}-{cat}-{random}';
            }
        }

        if (!$request->filled('product_sku_code')) {
            $request->merge([
                'product_sku_code' => GenerateUniqueSKU::generate(
                    model:        $product,
                    field:        'product_sku_code',
                    randomLength: 6,
                    prefix:       'PRD',
                    relations:    $relations,
                    format:       $format,
                ),
            ]);
        }

        return [
            'product_name'        => 'required|string|max:255',
            'product_sku_code'    => 'required|string|unique:products,product_sku_code|max:255',
            'barcode'             => 'nullable|string|max:255',
            'product_description' => 'nullable|string',
            'product_type'        => 'required|string|in:EXTERNAL_PURCHASED,INTERNAL_PRODUCED',
            'sale_method'         => 'sometimes|required|string|in:FIFO,LIFO',
            'product_category_id' => 'required|exists:product_categories,id',
            'base_uom_id'         => 'required|exists:unit_of_measurements,id',
            // supplier is required only for external purchase flow; caller should set rules accordingly
            'supplier_id'         => 'nullable|exists:suppliers,id',
            'warehouse_id'        => 'required|exists:warehouses,id',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // External purchase movement validation
    // User inputs: purchase_unit_price_in_usd + exchange_rate_from_usd_to_riel
    // CurrencyPricingHelper derives totals and missing fields before validation.
    // ─────────────────────────────────────────────────────────────────────────

    public function createExternalPurchaseMovementRules(): array
    {
        return [
            'quantity'                           => 'required|numeric|min:0.0001',
            'product_status'                     => 'sometimes|required|string|in:DRAFT,WORK_IN_PROGRESS,PARTIALLY_COMPLETED,COMPLETED,BLOCKED',

            // Purchase pricing (user supplies unit price + exchange rate; totals are derived)
            'purchase_unit_price_in_usd'         => 'required|numeric|min:0',
            'purchase_total_price_in_usd'        => 'nullable|numeric|min:0',
            'exchange_rate_from_usd_to_riel'     => 'required|numeric|min:0',
            'purchase_unit_price_in_riel'        => 'nullable|numeric|min:0',
            'purchase_total_price_in_riel'       => 'nullable|numeric|min:0',
            'exchange_rate_from_riel_to_usd'     => 'nullable|numeric|min:0',

            // Selling pricing (unit only — no totals)
            'selling_unit_price_in_usd'                  => 'required|numeric|min:0',
            'selling_unit_price_in_riel'                 => 'nullable|numeric|min:0',
            'selling_exchange_rate_from_usd_to_riel'     => 'required|numeric|min:0',
            'selling_exchange_rate_from_riel_to_usd'     => 'nullable|numeric|min:0',

            'movement_date'                      => 'required|date',
            'note'                               => 'nullable|string',

            // Raw materials are NOT allowed for external purchase flow
            'raw_materials'                      => 'prohibited',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal manufacturing movement validation
    // Purchase prices are always 0 (set programmatically before this runs).
    // Adds BOM (raw_materials array) validation.
    // ─────────────────────────────────────────────────────────────────────────

    public function createInternalManufacturingMovementRules(): array
    {
        $baseUomByRawMaterialId = [];

        return [
            'quantity'                                   => 'required|numeric|min:0.0001',
            'product_status'                             => 'required|string|in:DRAFT,WORK_IN_PROGRESS,PARTIALLY_COMPLETED,COMPLETED,BLOCKED',

            // Selling pricing (unit only — no totals)
            'selling_unit_price_in_usd'                  => 'required|numeric|min:0',
            'selling_unit_price_in_riel'                 => 'nullable|numeric|min:0',
            'selling_exchange_rate_from_usd_to_riel'     => 'required|numeric|min:0',
            'selling_exchange_rate_from_riel_to_usd'     => 'nullable|numeric|min:0',

            'movement_date'                              => 'required|date',
            'note'                                       => 'nullable|string',

            // Bill of materials
            'raw_materials'                              => 'required|array|min:1',
            'raw_materials.*.raw_material_id'            => 'required|exists:raw_materials,id|distinct',
            'raw_materials.*.quantity_per_unit'          => 'required|numeric|min:0.0001',
            'raw_materials.*.scrap_percentage'           => 'nullable|numeric|min:0|max:100',
            'raw_materials.*'                            => [
                function (string $attribute, mixed $value, $fail) use (&$baseUomByRawMaterialId) {
                    if (!is_array($value)) {
                        return;
                    }

                    $rawMaterialId = (int) ($value['raw_material_id'] ?? 0);
                    $quantityPerUnit = $value['quantity_per_unit'] ?? null;

                    if ($rawMaterialId <= 0 || $quantityPerUnit === null) {
                        return;
                    }

                    if (!array_key_exists($rawMaterialId, $baseUomByRawMaterialId)) {
                        $baseUomByRawMaterialId[$rawMaterialId] = (int) (RawMaterial::withTrashed()
                            ->whereKey($rawMaterialId)
                            ->value('base_uom_id') ?? 0);
                    }

                    $baseUomId = (int) ($baseUomByRawMaterialId[$rawMaterialId] ?? 0);
                    if ($baseUomId <= 0) {
                        return;
                    }

                    try {
                        UomQuantityGuard::assertQuantityByUomId(
                            $quantityPerUnit,
                            $baseUomId,
                            "{$attribute}.quantity_per_unit"
                        );
                    } catch (ValidationException $e) {
                        foreach ($e->errors() as $messages) {
                            foreach ($messages as $message) {
                                $fail($message);
                            }
                        }
                    }
                },
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Custom validation messages (shared by both movement rule sets)
    // ─────────────────────────────────────────────────────────────────────────

    public function movementValidationMessages(): array
    {
        return [
            'product_status.required'    => 'Product status is required.',
            'product_status.string'      => 'Product status must be a string.',
            'product_status.in'          => 'Invalid product status. Accepted values are: DRAFT, WORK_IN_PROGRESS, PARTIALLY_COMPLETED, COMPLETED, BLOCKED.',
            'raw_materials.prohibited'   => 'Raw materials are not allowed for the external purchase flow.',
            'sale_method.required'       => 'Sale method is required.',
            'sale_method.string'         => 'Sale method must be a string.',
            'sale_method.in'             => 'Invalid sale method. Accepted values are: FIFO, LIFO.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Image validation (max 4 files, shared by both flows)
    // ─────────────────────────────────────────────────────────────────────────

    public function createProductImageRules(): array
    {
        return [
            'images'   => 'nullable|array|max:4',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ];
    }

    // Optional product-level rules used when updating a product
    // Allows callers to validate only the fields they intend to change.
    public function updateProductRules(Request $request): array
    {
        return [
            'product_name'        => 'sometimes|string|max:255',
            'barcode'             => 'sometimes|nullable|string|max:255',
            'product_description' => 'sometimes|nullable|string',
            'sale_method'         => 'sometimes|required|string|in:FIFO,LIFO',
            'product_category_id' => 'sometimes|nullable|exists:product_categories,id',
            'base_uom_id'         => 'sometimes|nullable|exists:unit_of_measurements,id',
            'supplier_id'         => 'sometimes|nullable|exists:suppliers,id',
            'warehouse_id'        => 'sometimes|nullable|exists:warehouses,id',
        ];
    }
}
