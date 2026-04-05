<?php

namespace App\Service;

use App\Helpers\CurrencyPricingHelper;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\ProductHelper;
use App\Models\Product;
use App\Models\ProductRawMaterial;
use App\Models\ProductMovement;
use App\Models\ProductReorder;
use App\Models\ReorderProductRawMaterial;
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

    // Get Product Detail
    public function getProductDetail($id)
    {
        try {
            $product = Product::with([
                'category',
                'baseUom.category',
                'baseUom.category.unitOfMeasurements',
                'supplier',
                'warehouse',
                'productMovements' => function ($query) {
                    $query->orderBy('movement_date', 'desc')
                        ->with(['createdBy', 'lastUpdatedBy']);
                },
                'productImages',
                'productRawMaterials.rawMaterial', // Eager load raw material details for BOM
            ])->findOrFail($id);

            if (!$product) {
                return ResponseHelper::error('Product not found', 404);
            }

            // Total count of movements by movement_type
            $totalCountByMovementType = \App\Models\ProductMovement::where('product_id', $product->id)
                ->select('movement_type', DB::raw('COUNT(*) as total'))
                ->groupBy('movement_type')
                ->pluck('total', 'movement_type');

            // Calculate current quantity in stock from product movements (IN vs OUT)
            $currentQtyInStock = 0;
            foreach ($product->productMovements as $movement) {
                $qty = (float) ($movement->quantity ?? 0);
                $dir = is_object($movement->direction) ? $movement->direction->value : (string) $movement->direction;
                $currentQtyInStock += ($dir === 'OUT') ? (-$qty) : $qty;
            }

            // Determine stock status (simple): OUT_OF_STOCK or IN_STOCK
            $productStockStatus = $currentQtyInStock <= 0 ? 'OUT_OF_STOCK' : 'IN_STOCK';

            return ResponseHelper::success([
                'product' => $product,
                'current_qty_in_stock' => $currentQtyInStock,
                'product_stock_status' => $productStockStatus,
                'total_count_by_movement_type' => $totalCountByMovementType,
            ]);

            return ResponseHelper::success(['product' => $product]);
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

    // Reorder Product for External Purchased Product
    public function reorderExternalPurchasedProduct(Request $request, $productId)
    {
        try {
            $product = Product::findOrFail($productId);


            // First validate incoming request body for external purchase movement
            $rules = $this->productValidation->createExternalPurchaseMovementRules();
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            // Ensure product is of type EXTERNAL_PURCHASED
            $productTypeValue = ($product->product_type instanceof \BackedEnum)
                ? $product->product_type->value
                : (string) $product->product_type;

            if ($productTypeValue !== \App\Enums\ProductTypeEnum::EXTERNAL_PURCHASED->value) {
                return ResponseHelper::error(
                    'Invalid product type for external reorder',
                    422,
                    'Product must be an externally purchased product to create an external reorder.'
                );
            }

            // Enforce reorder flow defaults
            $request->merge([
                'product_id' => $product->id,
                'direction' => \App\Enums\StockDirectionEnum::IN->value,
                'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
                'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
            ]);

            // Only accept USD unit price + USD->Riel exchange rate as inputs;
            // compute everything else from quantity.
            $request->merge([
                'purchase_total_price_in_usd' => null,
                'purchase_unit_price_in_riel' => null,
                'purchase_total_price_in_riel' => null,
                'exchange_rate_from_riel_to_usd' => null,
            ]);

            // Always derive created_by / last_updated_by from the authenticated user
            $currentUserId = $this->getCurrentUserHelper->getUserId();
            $request->merge([
                'created_by'      => $currentUserId,
                'last_updated_by' => $currentUserId,
            ]);

            // Ensure selling-side fields exist (derive from last movement if present)
            $lastMovement = ProductMovement::where('product_id', $product->id)
                ->orderBy('movement_date', 'desc')
                ->first();

            if ($lastMovement) {
                $request->merge([
                    'selling_unit_price_in_usd' => $lastMovement->selling_unit_price_in_usd ?? 0,
                    'selling_exchange_rate_from_usd_to_riel' => $lastMovement->selling_exchange_rate_from_usd_to_riel ?? 0,
                ]);
            } else {
                $request->merge([
                    'selling_unit_price_in_usd' => $request->input('selling_unit_price_in_usd', 0),
                    'selling_exchange_rate_from_usd_to_riel' => $request->input('selling_exchange_rate_from_usd_to_riel', 0),
                ]);
            }

            CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

            $rules = $this->productValidation->createExternalPurchaseMovementRules();
            $validated = Validator::make($request->all(), $rules)->validate();

            // Ensure user attribution fields exist on the validated payload
            $validated['created_by'] = $validated['created_by'] ?? $this->getCurrentUserHelper->getUserId();
            $validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

            $movement = DB::transaction(function () use ($validated, $product) {
                return ProductMovement::create([
                    'product_id' => $product->id,
                    'direction' => \App\Enums\StockDirectionEnum::IN->value,
                    'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
                    'product_status' => \App\Enums\ProductStatusEnum::COMPLETED->value,
                    'quantity' => $validated['quantity'],
                    'is_sold' => false,
                    'movement_date' => $validated['movement_date'],
                    'note' => $validated['note'] ?? null,
                    'created_by' => $validated['created_by'],
                    'last_updated_by' => $validated['last_updated_by'],

                    // Purchase pricing (derived by CurrencyPricingHelper)
                    'purchase_unit_price_in_usd' => $validated['purchase_unit_price_in_usd'],
                    'purchase_total_price_in_usd' => $validated['purchase_total_price_in_usd'] ?? 0,
                    'exchange_rate_from_usd_to_riel' => $validated['exchange_rate_from_usd_to_riel'],
                    'purchase_unit_price_in_riel' => $validated['purchase_unit_price_in_riel'] ?? 0,
                    'purchase_total_price_in_riel' => $validated['purchase_total_price_in_riel'] ?? 0,
                    'exchange_rate_from_riel_to_usd' => $validated['exchange_rate_from_riel_to_usd'] ?? 0,

                    // Selling pricing (unit only — no totals)
                    'selling_unit_price_in_usd' => $validated['selling_unit_price_in_usd'],
                    'selling_unit_price_in_riel' => $validated['selling_unit_price_in_riel'] ?? 0,
                    'selling_exchange_rate_from_usd_to_riel' => $validated['selling_exchange_rate_from_usd_to_riel'],
                    'selling_exchange_rate_from_riel_to_usd' => $validated['selling_exchange_rate_from_riel_to_usd'] ?? 0,
                ]);
            });

            return ResponseHelper::success($movement, 'Product reordered successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    // Update Reorder Product (External Purchased Product)
    public function updateReorderExternalPurchasedProduct(Request $request, $productId, $movementId)
    {
        try {
            $product = Product::findOrFail($productId);
            $movement = ProductMovement::findOrFail($movementId);


            // First validate incoming request body for external purchase movement update
            $rules = $this->productValidation->createExternalPurchaseMovementRules();
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            // Ensure product is of type EXTERNAL_PURCHASED
            $productTypeValue = ($product->product_type instanceof \BackedEnum)
                ? $product->product_type->value
                : (string) $product->product_type;

            if ($productTypeValue !== \App\Enums\ProductTypeEnum::EXTERNAL_PURCHASED->value) {
                return ResponseHelper::error(
                    'Invalid product type for external reorder update',
                    422,
                    'Product must be an externally purchased product to update an external reorder.'
                );
            }

            // Enforce reorder flow defaults
            $request->merge([
                'product_id' => $product->id,
                'direction' => \App\Enums\StockDirectionEnum::IN->value,
                'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
                'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
            ]);

            // Only accept USD unit price + USD->Riel exchange rate as inputs;
            // compute everything else from quantity.
            $request->merge([
                'purchase_total_price_in_usd' => null,
                'purchase_unit_price_in_riel' => null,
                'purchase_total_price_in_riel' => null,
                'exchange_rate_from_riel_to_usd' => null,
            ]);

            // Preserve original created_by; update last_updated_by to the acting user
            $request->merge([
                'created_by' => $movement->created_by,
                'last_updated_by' => $this->getCurrentUserHelper->getUserId(),
            ]);

            // Check if the stock is already used (sold) to avoid data inconsistency
            if ($movement->is_sold === true) {
                return ResponseHelper::error('Cannot update used stock movement', 401, 'The reordered product has been sold. Data cannot be updated to avoid data inconsistency.');
            }

            // Ensure selling-side fields exist (derive from last movement if present)
            $lastMovement = ProductMovement::where('product_id', $product->id)
                ->orderBy('movement_date', 'desc')
                ->first();

            if ($lastMovement) {
                $request->merge([
                    'selling_unit_price_in_usd' => $lastMovement->selling_unit_price_in_usd ?? 0,
                    'selling_exchange_rate_from_usd_to_riel' => $lastMovement->selling_exchange_rate_from_usd_to_riel ?? 0,
                ]);
            } else {
                $request->merge([
                    'selling_unit_price_in_usd' => $request->input('selling_unit_price_in_usd', 0),
                    'selling_exchange_rate_from_usd_to_riel' => $request->input('selling_exchange_rate_from_usd_to_riel', 0),
                ]);
            }

            CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

            $rules = $this->productValidation->createExternalPurchaseMovementRules();
            $validated = Validator::make($request->all(), $rules)->validate();

            // Preserve created_by from original movement if available and ensure last_updated_by exists
            $validated['created_by'] = $validated['created_by'] ?? $movement->created_by ?? $this->getCurrentUserHelper->getUserId();
            $validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

            $movement = DB::transaction(function () use ($validated, $movement) {
                $movement->update([
                    'quantity' => $validated['quantity'],
                    'direction' => \App\Enums\StockDirectionEnum::IN->value,
                    'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
                    'movement_date' => $validated['movement_date'],
                    'note' => $validated['note'] ?? null,

                    // Purchase pricing
                    'purchase_unit_price_in_usd' => $validated['purchase_unit_price_in_usd'],
                    'purchase_total_price_in_usd' => $validated['purchase_total_price_in_usd'] ?? 0,
                    'exchange_rate_from_usd_to_riel' => $validated['exchange_rate_from_usd_to_riel'],
                    'purchase_unit_price_in_riel' => $validated['purchase_unit_price_in_riel'] ?? 0,
                    'purchase_total_price_in_riel' => $validated['purchase_total_price_in_riel'] ?? 0,
                    'exchange_rate_from_riel_to_usd' => $validated['exchange_rate_from_riel_to_usd'] ?? 0,

                    // Selling pricing
                    'selling_unit_price_in_usd' => $validated['selling_unit_price_in_usd'],
                    'selling_unit_price_in_riel' => $validated['selling_unit_price_in_riel'] ?? 0,
                    'selling_exchange_rate_from_usd_to_riel' => $validated['selling_exchange_rate_from_usd_to_riel'],
                    'selling_exchange_rate_from_riel_to_usd' => $validated['selling_exchange_rate_from_riel_to_usd'] ?? 0,

                    'last_updated_by' => $validated['last_updated_by'],
                ]);

                return $movement->fresh();
            });

            return ResponseHelper::success($movement, 'Product reorder updated successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    // Reorder Product for Internal Produced Product
    public function reorderInternalManufacturedProduct(Request $request, $productId) {
        try {
            $product = Product::findOrFail($productId);

            // If frontend does not provide BOM for reorder, fallback to product default BOM.
            $requestBomItems = $request->input('raw_materials', []);
            if (empty($requestBomItems)) {
                $request->merge([
                    'raw_materials' => $this->getDefaultBomFromProduct($product),
                ]);
            }


            // First validate incoming request body for internal manufacturing movement
            $rules = $this->productValidation->createInternalManufacturingMovementRules();
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            // Ensure product is of type INTERNAL_PRODUCED
            $productTypeValue = ($product->product_type instanceof \BackedEnum)
                ? $product->product_type->value
                : (string) $product->product_type;

            if ($productTypeValue !== \App\Enums\ProductTypeEnum::INTERNAL_PRODUCED->value) {
                return ResponseHelper::error(
                    'Invalid product type for internal manufacturing reorder',
                    422,
                    'Product must be an internally produced product to create an internal manufacturing reorder.'
                );
            }

            // Enforce reorder flow defaults
            $request->merge([
                'product_id' => $product->id,
                'direction' => \App\Enums\StockDirectionEnum::IN->value,
                'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
                'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
            ]);

            // Internal manufactured reorders carry no purchase cost (purchase=0).
            $request->merge([
                'purchase_unit_price_in_usd' => 0,
                'purchase_total_price_in_usd' => 0,
                'purchase_unit_price_in_riel' => 0,
                'purchase_total_price_in_riel' => 0,
                'exchange_rate_from_usd_to_riel' => 0,
                'exchange_rate_from_riel_to_usd' => null,
            ]);

            // Always derive created_by / last_updated_by from the authenticated user
            $currentUserId = $this->getCurrentUserHelper->getUserId();
            $request->merge([
                'created_by' => $currentUserId,
                'last_updated_by' => $currentUserId,
            ]);

            // Ensure selling-side fields exist (derive from last movement if present)
            $lastMovement = ProductMovement::where('product_id', $product->id)
                ->orderBy('movement_date', 'desc')
                ->first();

            if ($lastMovement) {
                $request->merge([
                    'selling_unit_price_in_usd' => $lastMovement->selling_unit_price_in_usd ?? 0,
                    'selling_exchange_rate_from_usd_to_riel' => $lastMovement->selling_exchange_rate_from_usd_to_riel ?? 0,
                ]);
            } else {
                $request->merge([
                    'selling_unit_price_in_usd' => $request->input('selling_unit_price_in_usd', 0),
                    'selling_exchange_rate_from_usd_to_riel' => $request->input('selling_exchange_rate_from_usd_to_riel', 0),
                ]);
            }

            // Reuse existing helper to derive any missing currency fields consistently
            CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

            // Validate BOM presence and movement fields
            $bomItems = $request->input('raw_materials', []);
            $shortfalls = $this->stockDeductionService->validateSufficientStock($bomItems);
            if (!empty($shortfalls)) {
                return ResponseHelper::error('Insufficient raw material stock', 422, $shortfalls);
            }

            $rules = $this->productValidation->createInternalManufacturingMovementRules();
            $validated = Validator::make($request->all(), $rules)->validate();

            // Ensure user attribution fields exist on the validated payload
            $validated['created_by'] = $validated['created_by'] ?? $this->getCurrentUserHelper->getUserId();
            $validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

            $movement = DB::transaction(function () use ($validated, $product, $bomItems) {
                $userId = $this->getCurrentUserHelper->getUserId();
                $movementDate = $validated['movement_date'] ?? now()->toDateTimeString();

                // 1) Create movement record for internal-produced reorder
                $movement = ProductMovement::create([
                    'product_id' => $product->id,
                    'direction' => \App\Enums\StockDirectionEnum::IN->value,
                    'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
                    'product_status' => \App\Enums\ProductStatusEnum::COMPLETED->value,
                    'quantity' => $validated['quantity'],
                    'is_sold' => false,
                    'movement_date' => $movementDate,
                    'note' => $validated['note'] ?? null,
                    'created_by' => $validated['created_by'],
                    'last_updated_by' => $validated['last_updated_by'],

                    // Purchase pricing intentionally zero for internal manufacturing
                    'purchase_unit_price_in_usd' => 0,
                    'purchase_total_price_in_usd' => 0,
                    'exchange_rate_from_usd_to_riel' => 0,
                    'purchase_unit_price_in_riel' => 0,
                    'purchase_total_price_in_riel' => 0,
                    'exchange_rate_from_riel_to_usd' => 0,

                    // Selling pricing (unit only — no totals)
                    'selling_unit_price_in_usd' => $validated['selling_unit_price_in_usd'] ?? 0,
                    'selling_unit_price_in_riel' => $validated['selling_unit_price_in_riel'] ?? 0,
                    'selling_exchange_rate_from_usd_to_riel' => $validated['selling_exchange_rate_from_usd_to_riel'] ?? 0,
                    'selling_exchange_rate_from_riel_to_usd' => $validated['selling_exchange_rate_from_riel_to_usd'] ?? 0,
                ]);

                // Persist reorder snapshot root record.
                $productReorder = ProductReorder::create([
                    'product_id' => $product->id,
                    'product_movement_id' => $movement->id,
                    'quantity' => $validated['quantity'],
                    'status' => \App\Enums\ProductStatusEnum::COMPLETED->value,
                    'is_finalized' => false,
                    'created_by' => $validated['created_by'],
                    'last_updated_by' => $validated['last_updated_by'],
                    'notes' => $validated['note'] ?? null,
                ]);

                // Persist reorder BOM snapshot rows.
                foreach ($bomItems as $item) {
                    ReorderProductRawMaterial::create([
                        'product_reorder_id' => $productReorder->id,
                        'raw_material_id' => $item['raw_material_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }

                $referenceToken = $this->buildReorderReferenceToken((int) $movement->id);

                // 2) Deduct raw material stock respecting FIFO/LIFO for this production
                $this->stockDeductionService->deductStock(
                    $bomItems,
                    $product->id,
                    $userId,
                    $movementDate,
                    $referenceToken
                );

                return [
                    'movement' => $movement,
                    'product_reorder_id' => $productReorder->id,
                ];
            });

            return ResponseHelper::success($movement, 'Product reordered (internal manufacturing) successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    // Update Reorder Product for Internal Produced Product
    public function updateReorderInternalManufacturedProduct(Request $request, $productId, $movementId) {
        try {
            $product = Product::findOrFail($productId);
            $movement = ProductMovement::findOrFail($movementId);

            $productReorder = ProductReorder::where('product_id', $product->id)
                ->where('product_movement_id', $movement->id)
                ->first();

            if (!$productReorder) {
                return ResponseHelper::error(
                    'Reorder snapshot not found',
                    404,
                    'This reorder does not have BOM snapshot data. Only reorders created with the new snapshot flow can be updated.'
                );
            }

            if ($productReorder->is_finalized) {
                return ResponseHelper::error('Cannot update finalized reorder', 401, 'The reorder has been finalized and can no longer be updated.');
            }

            // Default update payload to existing snapshot if BOM is omitted.
            $requestBomItems = $request->input('raw_materials', []);
            if (empty($requestBomItems)) {
                $request->merge([
                    'raw_materials' => $productReorder->bomItems()
                        ->get(['raw_material_id', 'quantity'])
                        ->map(fn ($item) => [
                            'raw_material_id' => (int) $item->raw_material_id,
                            'quantity' => (float) $item->quantity,
                        ])
                        ->values()
                        ->all(),
                ]);
            }


            // First validate incoming request body for internal manufacturing movement update
            $rules = $this->productValidation->createInternalManufacturingMovementRules();
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            // Ensure product is of type INTERNAL_PRODUCED
            $productTypeValue = ($product->product_type instanceof \BackedEnum)
                ? $product->product_type->value
                : (string) $product->product_type;

            if ($productTypeValue !== \App\Enums\ProductTypeEnum::INTERNAL_PRODUCED->value) {
                return ResponseHelper::error(
                    'Invalid product type for internal manufacturing reorder update',
                    422,
                    'Product must be an internally produced product to update an internal manufacturing reorder.'
                );
            }

            // Enforce reorder flow defaults
            $request->merge([
                'product_id' => $product->id,
                'direction' => \App\Enums\StockDirectionEnum::IN->value,
                'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
                'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
            ]);

            // Purchase side remains zero for internal manufacturing
            $request->merge([
                'purchase_unit_price_in_usd' => 0,
                'purchase_total_price_in_usd' => 0,
                'purchase_unit_price_in_riel' => 0,
                'purchase_total_price_in_riel' => 0,
                'exchange_rate_from_usd_to_riel' => 0,
                'exchange_rate_from_riel_to_usd' => null,
            ]);

            // Preserve original created_by; update last_updated_by to the acting user
            $request->merge([
                'created_by' => $movement->created_by,
                'last_updated_by' => $this->getCurrentUserHelper->getUserId(),
            ]);

            // Check if the stock is already used (sold) to avoid data inconsistency
            if ($movement->is_sold === true) {
                return ResponseHelper::error('Cannot update used stock movement', 401, 'The reordered product has been sold. Data cannot be updated to avoid data inconsistency.');
            }

            // Ensure selling-side fields exist (derive from last movement if present)
            $lastMovement = ProductMovement::where('product_id', $product->id)
                ->orderBy('movement_date', 'desc')
                ->first();

            if ($lastMovement) {
                $request->merge([
                    'selling_unit_price_in_usd' => $lastMovement->selling_unit_price_in_usd ?? 0,
                    'selling_exchange_rate_from_usd_to_riel' => $lastMovement->selling_exchange_rate_from_usd_to_riel ?? 0,
                ]);
            } else {
                $request->merge([
                    'selling_unit_price_in_usd' => $request->input('selling_unit_price_in_usd', 0),
                    'selling_exchange_rate_from_usd_to_riel' => $request->input('selling_exchange_rate_from_usd_to_riel', 0),
                ]);
            }

            CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

            $rules = $this->productValidation->createInternalManufacturingMovementRules();
            $validated = Validator::make($request->all(), $rules)->validate();

            // Preserve created_by from original movement if available and ensure last_updated_by exists
            $validated['created_by'] = $validated['created_by'] ?? $movement->created_by ?? $this->getCurrentUserHelper->getUserId();
            $validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

            $bomItems = $validated['raw_materials'] ?? [];

            DB::beginTransaction();
            try {
                $referenceToken = $this->buildReorderReferenceToken((int) $movement->id);
                $deletedRawMaterialIds = $this->stockDeductionService->deleteReorderConsumptionMovementsByToken($referenceToken);

                $existingBomRawMaterialIds = $productReorder->bomItems()
                    ->pluck('raw_material_id')
                    ->all();

                // Simplified approach: remove all old BOM rows then reinsert from request.
                $productReorder->bomItems()->delete();

                $rebuildIds = array_values(array_unique(array_merge($deletedRawMaterialIds, $existingBomRawMaterialIds)));
                $this->stockDeductionService->rebuildInUsedFlags($rebuildIds);

                $shortfalls = $this->stockDeductionService->validateSufficientStock($bomItems);
                if (!empty($shortfalls)) {
                    DB::rollBack();
                    return ResponseHelper::error('Insufficient raw material stock', 422, $shortfalls);
                }

                foreach ($bomItems as $item) {
                    ReorderProductRawMaterial::create([
                        'product_reorder_id' => $productReorder->id,
                        'raw_material_id' => $item['raw_material_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }

                $this->stockDeductionService->deductStock(
                    $bomItems,
                    $product->id,
                    (int) $validated['last_updated_by'],
                    $validated['movement_date'],
                    $referenceToken
                );

                $movement->update([
                    'quantity' => $validated['quantity'],
                    'direction' => \App\Enums\StockDirectionEnum::IN->value,
                    'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
                    'movement_date' => $validated['movement_date'],
                    'note' => $validated['note'] ?? null,

                    // Purchase pricing remains zero
                    'purchase_unit_price_in_usd' => 0,
                    'purchase_total_price_in_usd' => 0,
                    'exchange_rate_from_usd_to_riel' => 0,
                    'purchase_unit_price_in_riel' => 0,
                    'purchase_total_price_in_riel' => 0,
                    'exchange_rate_from_riel_to_usd' => 0,

                    // Selling pricing
                    'selling_unit_price_in_usd' => $validated['selling_unit_price_in_usd'] ?? 0,
                    'selling_unit_price_in_riel' => $validated['selling_unit_price_in_riel'] ?? 0,
                    'selling_exchange_rate_from_usd_to_riel' => $validated['selling_exchange_rate_from_usd_to_riel'] ?? 0,
                    'selling_exchange_rate_from_riel_to_usd' => $validated['selling_exchange_rate_from_riel_to_usd'] ?? 0,

                    'last_updated_by' => $validated['last_updated_by'],
                ]);

                $productReorder->update([
                    'quantity' => $validated['quantity'],
                    'status' => $validated['product_status'] ?? $productReorder->status,
                    'last_updated_by' => $validated['last_updated_by'],
                    'notes' => $validated['note'] ?? null,
                ]);

                DB::commit();

                $movement = $movement->fresh();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return ResponseHelper::success($movement, 'Product reorder (internal manufacturing) updated successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    // Create Product Scrap Movement
    public function createScrapMovement(Request $request, $productId) {
        try {
            $product = Product::findOrFail($productId);

            $rules = [
                'quantity' => 'required|numeric|min:0.0001',
                'movement_date' => 'nullable|date',
                'note' => 'nullable|string',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $validated = $validator->validate();

            // Calculate current stock for the product
            $currentQtyInStock = 0;
            $movements = ProductMovement::where('product_id', $product->id)->get();
            foreach ($movements as $m) {
                $qty = (float) ($m->quantity ?? 0);
                $dir = is_object($m->direction) ? $m->direction->value : (string) $m->direction;
                $currentQtyInStock += ($dir === 'OUT') ? (-$qty) : $qty;
            }

            if ($currentQtyInStock < (float) $validated['quantity']) {
                return ResponseHelper::error('Insufficient product stock to scrap', 422, ['available_qty' => $currentQtyInStock]);
            }

            $currentUserId = $this->getCurrentUserHelper->getUserId();
            $movementDate = $validated['movement_date'] ?? now()->toDateTimeString();

            $movement = DB::transaction(function () use ($product, $validated, $currentUserId, $movementDate) {
                return ProductMovement::create([
                    'product_id' => $product->id,
                    'direction' => \App\Enums\StockDirectionEnum::OUT->value,
                    'movement_type' => \App\Enums\ProductStockMovementTypeEnum::SCRAP->value,
                    'product_status' => \App\Enums\ProductStatusEnum::COMPLETED->value,
                    'quantity' => $validated['quantity'],
                    'is_sold' => false,
                    'movement_date' => $movementDate,
                    'note' => $validated['note'] ?? null,
                    'created_by' => $currentUserId,
                    'last_updated_by' => $currentUserId,

                    // All financial fields set to zero for scrapped stock
                    'purchase_unit_price_in_usd' => 0,
                    'purchase_total_price_in_usd' => 0,
                    'exchange_rate_from_usd_to_riel' => 0,
                    'purchase_unit_price_in_riel' => 0,
                    'purchase_total_price_in_riel' => 0,
                    'exchange_rate_from_riel_to_usd' => 0,

                    'selling_unit_price_in_usd' => 0,
                    'selling_unit_price_in_riel' => 0,
                    'selling_exchange_rate_from_usd_to_riel' => 0,
                    'selling_exchange_rate_from_riel_to_usd' => 0,
                ]);
            });

            return ResponseHelper::success($movement, 'Product scrapped successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }


    // Update Product Scrap Movement
    public function updateScrapMovement(Request $request, $productId, $movementId) {
        try {
            $product = Product::findOrFail($productId);
            $movement = ProductMovement::findOrFail($movementId);

            // Ensure movement belongs to the product
            if ($movement->product_id !== $product->id) {
                return ResponseHelper::error('Movement does not belong to product', 422);
            }

            // Ensure this is a SCRAP movement
            $movementTypeValue = (is_object($movement->movement_type) ? $movement->movement_type->value : (string) $movement->movement_type);
            if ($movementTypeValue !== \App\Enums\ProductStockMovementTypeEnum::SCRAP->value) {
                return ResponseHelper::error('Movement is not a scrap movement', 422);
            }

            // Validate input
            $rules = [
                'quantity' => 'required|numeric|min:0.0001',
                'movement_date' => 'nullable|date',
                'note' => 'nullable|string',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $validated = $validator->validate();

            // Block updates for movements that have been used/sold
            if ($movement->is_sold === true) {
                return ResponseHelper::error('Cannot update used stock movement', 401, 'The scrap movement has been sold/used. Data cannot be updated to avoid data inconsistency.');
            }

            // Calculate current stock and compute available qty when removing current movement
            $currentQtyInStock = 0;
            $movements = ProductMovement::where('product_id', $product->id)->get();
            foreach ($movements as $m) {
                $qty = (float) ($m->quantity ?? 0);
                $dir = is_object($m->direction) ? $m->direction->value : (string) $m->direction;
                $currentQtyInStock += ($dir === 'OUT') ? (-$qty) : $qty;
            }

            // Since the existing movement is currently counted (as OUT), add it back to compute availability
            $availableQty = $currentQtyInStock + (float) ($movement->quantity ?? 0);

            if ($availableQty < (float) $validated['quantity']) {
                return ResponseHelper::error('Insufficient product stock to update scrap quantity', 422, ['available_qty' => $availableQty]);
            }

            $currentUserId = $this->getCurrentUserHelper->getUserId();
            $movementDate = $validated['movement_date'] ?? now()->toDateTimeString();

            $movement = DB::transaction(function () use ($movement, $validated, $currentUserId, $movementDate) {
                $movement->update([
                    'quantity' => $validated['quantity'],
                    'direction' => \App\Enums\StockDirectionEnum::OUT->value,
                    'movement_type' => \App\Enums\ProductStockMovementTypeEnum::SCRAP->value,
                    'movement_date' => $movementDate,
                    'note' => $validated['note'] ?? null,
                    // financial fields remain zero for scrap
                    'purchase_unit_price_in_usd' => 0,
                    'purchase_total_price_in_usd' => 0,
                    'exchange_rate_from_usd_to_riel' => 0,
                    'purchase_unit_price_in_riel' => 0,
                    'purchase_total_price_in_riel' => 0,
                    'exchange_rate_from_riel_to_usd' => 0,
                    'selling_unit_price_in_usd' => 0,
                    'selling_unit_price_in_riel' => 0,
                    'selling_exchange_rate_from_usd_to_riel' => 0,
                    'selling_exchange_rate_from_riel_to_usd' => 0,
                    'last_updated_by' => $currentUserId,
                ]);

                return $movement->fresh();
            });

            return ResponseHelper::success($movement, 'Product scrap updated successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    private function getDefaultBomFromProduct(Product $product): array
    {
        return ProductRawMaterial::where('product_id', $product->id)
            ->get(['raw_material_id', 'quantity'])
            ->map(fn ($item) => [
                'raw_material_id' => (int) $item->raw_material_id,
                'quantity' => (float) $item->quantity,
            ])
            ->values()
            ->all();
    }

    private function buildReorderReferenceToken(int $movementId): string
    {
        return "REORDER_MOVEMENT_ID:{$movementId}";
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
