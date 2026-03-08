<?php

namespace App\Service;

use App\Helpers\CurrencyPricingHelper;
use App\Helpers\FileUploadHelper;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductRawMaterial;
use App\QueryBuilders\ProductQueryBuilder;
use App\Validations\ProductValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductService
{
    protected ProductValidation               $productValidation;
    protected ProductQueryBuilder             $productQueryBuilder;
    protected ProductMovementService          $productMovementService;
    protected RawMaterialStockDeductionService $stockDeductionService;
    protected GetCurrentUserHelper            $getCurrentUserHelper;

    public function __construct(
        ProductValidation                $productValidation,
        ProductQueryBuilder              $productQueryBuilder,
        ProductMovementService           $productMovementService,
        RawMaterialStockDeductionService $stockDeductionService,
        GetCurrentUserHelper             $getCurrentUserHelper
    ) {
        $this->productValidation       = $productValidation;
        $this->productQueryBuilder     = $productQueryBuilder;
        $this->productMovementService  = $productMovementService;
        $this->stockDeductionService   = $stockDeductionService;
        $this->getCurrentUserHelper    = $getCurrentUserHelper;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // List products (paginated, filterable, sortable)
    // ─────────────────────────────────────────────────────────────────────────

    public function getAllProducts(Request $request)
    {
        try {
            return $this->productQueryBuilder->productBuilder($request);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create: External Purchased Product
    //
    // Flow:
    //   1. Set defaults (direction, movement_type, movement_date, user IDs)
    //   2. Derive missing currency fields via CurrencyPricingHelper
    //   3. Validate product + movement + images; collect ALL errors up-front
    //   4. DB transaction: create Product → ProductMovement → ProductImages
    // ─────────────────────────────────────────────────────────────────────────

    public function createExternalPurchasedProduct(Request $request)
    {
        try {
            $this->applyCommonDefaults($request);

            CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

            $errors = $this->collectValidationErrors($request, [
                $this->productValidation->createProductRules($request),
                $this->productValidation->createExternalPurchaseMovementRules(),
                $this->productValidation->createProductImageRules(),
            ], $this->productValidation->movementValidationMessages());

            if (!empty($errors)) {
                return ResponseHelper::validation($errors, 'Validation Error');
            }

            return DB::transaction(function () use ($request) {
                $userId = $this->getCurrentUserHelper->getUserId();

                // 1) Create product record
                $product = $this->createProductRecord($request);

                // 2) Re-validate movement fields inside transaction for a clean array
                $movementValidated = Validator::make(
                    $request->all(),
                    $this->productValidation->createExternalPurchaseMovementRules()
                )->validate();

                // 3) Create initial movement (EXTERNAL_PURCHASED / IN / COMPLETED)
                $this->productMovementService->createExternalPurchaseMovement(
                    $product,
                    $movementValidated,
                    $userId
                );

                // 4) Upload and store product images
                $this->handleImageUpload($request, $product);

                return ResponseHelper::success(
                    ['product' => $this->freshProduct($product)],
                    'Product created successfully',
                    201
                );
            });
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create: Internal Manufactured Product
    //
    // Flow:
    //   1. Set defaults; force all purchase prices to 0
    //   2. Derive missing selling currency fields via CurrencyPricingHelper
    //   3. Validate product + movement (with BOM) + images; collect ALL errors
    //   4. Check raw material stock (FIFO/LIFO) — return error if insufficient
    //   5. DB transaction:
    //        create Product → ProductRawMaterial BOM records
    //        → ProductMovement → deduct raw material stock (PRODUCTION_RECEIPT OUT)
    //        → ProductImages
    // ─────────────────────────────────────────────────────────────────────────

    public function createInternalManufacturedProduct(Request $request)
    {
        try {
            $this->applyCommonDefaults($request);
            $this->forceZeroPurchasePrices($request);

            // Only selling side needs derivation; purchase is already 0
            CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

            $errors = $this->collectValidationErrors($request, [
                $this->productValidation->createProductRules($request),
                $this->productValidation->createInternalManufacturingMovementRules(),
                $this->productValidation->createProductImageRules(),
            ], $this->productValidation->movementValidationMessages());

            if (!empty($errors)) {
                return ResponseHelper::validation($errors, 'Validation Error');
            }

            // Check raw material availability (FIFO/LIFO) before opening the transaction
            $bomItems   = $request->input('raw_materials', []);
            $shortfalls = $this->stockDeductionService->validateSufficientStock($bomItems);

            if (!empty($shortfalls)) {
                return ResponseHelper::error(
                    'Insufficient raw material stock',
                    422,
                    $shortfalls
                );
            }

            return DB::transaction(function () use ($request, $bomItems) {
                $userId       = $this->getCurrentUserHelper->getUserId();
                $movementDate = $request->input('movement_date', now()->toDateTimeString());

                // 1) Create product record
                $product = $this->createProductRecord($request);

                // 2) Persist BOM pivot records
                foreach ($bomItems as $item) {
                    ProductRawMaterial::create([
                        'product_id'      => $product->id,
                        'raw_material_id' => $item['raw_material_id'],
                        'quantity'        => $item['quantity'],
                    ]);
                }

                // 3) Re-validate movement fields inside transaction for a clean array
                $movementValidated = Validator::make(
                    $request->all(),
                    $this->productValidation->createInternalManufacturingMovementRules()
                )->validate();

                // 4) Create initial movement (INTERNAL_MANUFACTURED / IN / COMPLETED)
                $this->productMovementService->createInternalManufacturingMovement(
                    $product,
                    $movementValidated,
                    $userId
                );

                // 5) Deduct raw material stock respecting FIFO/LIFO
                $this->stockDeductionService->deductStock(
                    $bomItems,
                    $product->id,
                    $userId,
                    $movementDate
                );

                // 6) Upload and store product images
                $this->handleImageUpload($request, $product);

                return ResponseHelper::success(
                    ['product' => $this->freshProduct($product)],
                    'Product created successfully',
                    201
                );
            });
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Merge shared request defaults before validation.
     */
    private function applyCommonDefaults(Request $request): void
    {
        if (!$request->filled('movement_date')) {
            $request->merge(['movement_date' => now()->toDateTimeString()]);
        }

        $userId = $this->getCurrentUserHelper->getUserId();
        $request->merge([
            'created_by'      => $userId,
            'last_updated_by' => $userId,
        ]);
    }

    /**
     * Force all purchase price fields to 0 for internally manufactured products.
     */
    private function forceZeroPurchasePrices(Request $request): void
    {
        $request->merge([
            'purchase_unit_price_in_usd'     => 0,
            'purchase_total_price_in_usd'    => 0,
            'exchange_rate_from_usd_to_riel' => 0,
            'purchase_unit_price_in_riel'    => 0,
            'purchase_total_price_in_riel'   => 0,
            'exchange_rate_from_riel_to_usd' => 0,
        ]);
    }

    /**
     * Run multiple rule sets against the request and merge all errors at once.
     * Pass $messages to apply custom error message overrides across all rule sets.
     */
    private function collectValidationErrors(Request $request, array $ruleSets, array $messages = []): array
    {
        $errors = [];

        $mergeErrors = static function (array $base, array $incoming): array {
            foreach ($incoming as $field => $msgs) {
                $base[$field] = array_values(array_unique(array_merge($base[$field] ?? [], $msgs)));
            }
            return $base;
        };

        foreach ($ruleSets as $rules) {
            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                $errors = $mergeErrors($errors, $validator->errors()->toArray());
            }
        }

        return $errors;
    }

    /**
     * Create the Product model record using validated data.
     * SKU is pre-generated by createProductRules(); calling it again is safe
     * because the SKU is already merged into the request.
     */
    private function createProductRecord(Request $request): Product
    {
        $validated = Validator::make(
            $request->all(),
            $this->productValidation->createProductRules($request)
        )->validate();

        return Product::create([
            'product_name'        => $validated['product_name'],
            'product_sku_code'    => $validated['product_sku_code'],
            'barcode'             => $validated['barcode']             ?? null,
            'product_description' => $validated['product_description'] ?? null,
            'product_category_id' => $validated['product_category_id'],
            'base_uom_id'         => $validated['base_uom_id'],
            'sale_uom_id'         => $validated['sale_uom_id'],
            'purchase_uom_id'     => $validated['purchase_uom_id']    ?? null,
            'supplier_id'         => $validated['supplier_id'],
            'warehouse_id'        => $validated['warehouse_id'],
        ]);
    }

    /**
     * Upload images (if provided) and associate them with the product.
     */
    private function handleImageUpload(Request $request, Product $product): void
    {
        $files = [];

        if ($request->hasFile('images')) {
            $files = $request->file('images');
        } elseif ($request->hasFile('image')) {
            $files = [$request->file('image')];
        }

        if (empty($files)) {
            return;
        }

        $files = array_slice($files, 0, 4);

        foreach ($files as $file) {
            $imageUrl = FileUploadHelper::uploadSingle($file, 'products', null);
            ProductImage::create([
                'product_id' => $product->id,
                'image'      => $imageUrl,
            ]);
        }
    }

    /**
     * Return the product freshly loaded with all relevant relations.
     */
    private function freshProduct(Product $product): Product
    {
        return $product->fresh([
            'category'                        => fn ($q) => $q->withTrashed(),
            'supplier'                        => fn ($q) => $q->withTrashed(),
            'warehouse'                       => fn ($q) => $q->withTrashed(),
            'uom'                             => fn ($q) => $q->withTrashed(),
            'productMovements.createdBy',
            'productMovements.lastUpdatedBy',
            'productRawMaterials.rawMaterial',
            'productImages',
        ]);
    }
}