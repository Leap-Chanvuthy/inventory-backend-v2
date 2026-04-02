<?php

namespace App\Service;

use App\Helpers\CurrencyPricingHelper;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\ProductHelper;
use App\Models\Product;
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
            ProductHelper::applyCommonDefaults($request, $this->getCurrentUserHelper);

            // Force product_type to EXTERNAL_PURCHASED regardless of frontend input
            $request->merge(['product_type' => \App\Enums\ProductTypeEnum::EXTERNAL_PURCHASED->value]);

            CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

            // For external purchase flow supplier is required — ensure rule is enforced by validator
            $productRules = $this->productValidation->createProductRules($request);
            $productRules['supplier_id'] = 'required|exists:suppliers,id';

            $errors = ProductHelper::collectValidationErrors($request, [
                $productRules,
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
                ProductHelper::handleImageUpload($request, $product);

                return ResponseHelper::success(
                    ['product' => ProductHelper::freshProduct($product)],
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
            ProductHelper::applyCommonDefaults($request, $this->getCurrentUserHelper);
            ProductHelper::forceZeroPurchasePrices($request);

            // Force product_type to INTERNAL_PRODUCED and explicitly ignore any supplier provided by frontend
            $request->merge([
                'product_type' => \App\Enums\ProductTypeEnum::INTERNAL_PRODUCED->value,
                'supplier_id' => null,
            ]);

            // Only selling side needs derivation; purchase is already 0
            CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

            // Ensure supplier is not required for internal manufacturing
            $productRules = $this->productValidation->createProductRules($request);
            $productRules['supplier_id'] = 'nullable|exists:suppliers,id';

            // Provide an explanatory message for supplier being optional in this flow
            $customMessages = array_merge($this->productValidation->movementValidationMessages(), [
                'supplier_id.nullable' => 'Supplier is not required for internal manufacturing and will be ignored if provided.',
            ]);

            $errors = ProductHelper::collectValidationErrors($request, [
                $productRules,
                $this->productValidation->createInternalManufacturingMovementRules(),
                $this->productValidation->createProductImageRules(),
            ], $customMessages);

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

                // 4) Create initial movement (INTERNAL_PRODUCED / IN / COMPLETED)
                $this->productMovementService->createInternalProductionMovement(
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
                ProductHelper::handleImageUpload($request, $product);

                return ResponseHelper::success(
                    ['product' => ProductHelper::freshProduct($product)],
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

    // Helper functions have been moved to App\Helpers\ProductHelper to keep
    // this service focused on core CRUD orchestration.

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
            'product_type'        => $validated['product_type'],
            'product_sku_code'    => $validated['product_sku_code'],
            'barcode'             => $validated['barcode']             ?? null,
            'product_description' => $validated['product_description'] ?? null,
            'product_category_id' => $validated['product_category_id'],
            'base_uom_id'         => $validated['base_uom_id'],
            'supplier_id'         => $validated['supplier_id'],
            'warehouse_id'        => $validated['warehouse_id'],
        ]);
    }

    // Image upload and fresh-loading helpers moved to ProductHelper.
}