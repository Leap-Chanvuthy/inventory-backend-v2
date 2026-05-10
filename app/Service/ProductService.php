<?php

namespace App\Service;

use App\Helpers\CurrencyPricingHelper;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\ProductHelper;
use App\Helpers\UomQuantityGuard;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\RMStockMovement;
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
    protected ManufacturingService            $manufacturingService;
    protected GetCurrentUserHelper            $getCurrentUserHelper;
    protected ProductPnLService               $productPnLService;
    protected ProductHelper                   $productHelper;
    protected AuditLoggerService              $auditLoggerService;

    public function __construct(
        ProductValidation                $productValidation,
        ProductQueryBuilder              $productQueryBuilder,
        ProductMovementService           $productMovementService,
        ManufacturingService             $manufacturingService,
        GetCurrentUserHelper             $getCurrentUserHelper,
        ProductPnLService                $productPnLService,
        ProductHelper                    $productHelper,
        AuditLoggerService               $auditLoggerService
    ) {
        $this->productValidation       = $productValidation;
        $this->productQueryBuilder     = $productQueryBuilder;
        $this->productMovementService  = $productMovementService;
        $this->manufacturingService    = $manufacturingService;
        $this->getCurrentUserHelper    = $getCurrentUserHelper;
        $this->productPnLService       = $productPnLService;
        $this->productHelper          = $productHelper;
        $this->auditLoggerService      = $auditLoggerService;
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
    
    public function getAllProductMovements(Request $request, $productId)
    {
        try {
            $product = Product::findOrFail($productId);
            return $this->productQueryBuilder->productMovementBuilder($request, $productId);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    

    public function getTrashedProducts(Request $request)
    {
        try {
            return $this->productQueryBuilder->productBuilder($request, true);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    // Get Product Detail
    public function getProductDetail(int $id)
    {
        try {
            $product = Product::with([
                'category',
                'baseUom.category',
                'baseUom.category.unitOfMeasurements',
                'supplier',
                'warehouse',
                'productImages',
                'productRawMaterials.rawMaterial.uom',
            ])->findOrFail($id);

            if (!$product) {
                return ResponseHelper::error('Product not found', 404);
            }

            // Return first movement on the list (INTERNAL_PRODUCED or EXTERNAL_PURCHASED) to frontend for quick reference of sold state without scanning all movements.
            $initialMovement = \App\Models\ProductMovement::where('product_id', $product->id)
                ->whereIn('movement_type', [
                    \App\Enums\ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value,
                    \App\Enums\ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value,
                ])
                ->orderBy('movement_date', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            // Total count of movements by movement_type
            $totalCountByMovementType = \App\Models\ProductMovement::where('product_id', $product->id)
                ->select('movement_type', DB::raw('COUNT(*) as total'))
                ->groupBy('movement_type')
                ->pluck('total', 'movement_type');

            // Calculate current quantity in stock from movements (IN vs OUT) without eager-loading movements
            $currentQtyInStock = (float) ProductMovement::where('product_id', $product->id)
                ->selectRaw('COALESCE(SUM(CASE WHEN direction = "IN" THEN quantity ELSE -quantity END), 0) as current_qty')
                ->value('current_qty');

            // Determine stock status (simple): OUT_OF_STOCK or IN_STOCK
            $productStockStatus = $currentQtyInStock <= 0 ? 'OUT_OF_STOCK' : 'IN_STOCK';

            // Enrich BOM raw materials with live stock availability for update/reorder UI.
            $rawMaterialIds = $product->productRawMaterials
                ->pluck('raw_material_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($rawMaterialIds)) {
                $totalInByRawMaterial = RMStockMovement::query()
                    ->whereIn('raw_material_id', $rawMaterialIds)
                    ->where('direction', 'IN')
                    ->select('raw_material_id', DB::raw('SUM(quantity) as total_in'))
                    ->groupBy('raw_material_id')
                    ->pluck('total_in', 'raw_material_id');

                $totalOutByRawMaterial = RMStockMovement::query()
                    ->whereIn('raw_material_id', $rawMaterialIds)
                    ->where('direction', 'OUT')
                    ->select('raw_material_id', DB::raw('SUM(quantity) as total_out'))
                    ->groupBy('raw_material_id')
                    ->pluck('total_out', 'raw_material_id');

                foreach ($product->productRawMaterials as $bomItem) {
                    if (!$bomItem->rawMaterial) {
                        continue;
                    }

                    $rawMaterialId = (int) $bomItem->raw_material_id;
                    $totalIn = (float) ($totalInByRawMaterial[$rawMaterialId] ?? 0);
                    $totalOut = (float) ($totalOutByRawMaterial[$rawMaterialId] ?? 0);
                    $currentQty = round(max(0, $totalIn - $totalOut) , 2);

                    $bomItem->rawMaterial->setAttribute('current_qty_in_stock', $currentQty);
                    $bomItem->rawMaterial->setAttribute('stock_availability', $currentQty);
                }
            }

            // Get P&L data for the product
            $productPnL = $this->productPnLService->getProductPnL($product->id);

            // Frontend helper: expose sold state of the initial stock movement
            // (EXTERNAL_PURCHASED or INTERNAL_PRODUCED) so UI doesn't need to scan all movements.
            $productTypeValue = ($product->product_type instanceof \BackedEnum)
                ? $product->product_type->value
                : (string) $product->product_type;
            $initialMovementType = $productTypeValue === \App\Enums\ProductTypeEnum::INTERNAL_PRODUCED->value
                ? \App\Enums\ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value
                : \App\Enums\ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value;

            $initialMovement = \App\Models\ProductMovement::where('product_id', $product->id)
                ->where('movement_type', $initialMovementType)
                ->orderBy('movement_date', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            $isInitialMovementSold = (bool) ($initialMovement->is_sold ?? false);

            return ResponseHelper::success([
                'is_sold' => $isInitialMovementSold,
                'allow_bom_update' => !$isInitialMovementSold,
                'product' => $product,
                'initial_movement' => $initialMovement,
                'current_qty_in_stock' => $currentQtyInStock,
                'product_stock_status' => $productStockStatus,
                'total_count_by_movement_type' => $totalCountByMovementType,
                'product_pnl' => $productPnL,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

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

            UomQuantityGuard::assertQuantityByUomId(
                $request->input('quantity'),
                (int) $request->input('base_uom_id'),
                'quantity'
            );

            $result = DB::transaction(function () use ($request) {
                $userId = $this->getCurrentUserHelper->getUserId();

                // 1) Create product record
                $product = $this->productHelper->createProductRecord($request, $this->productValidation);

                // 2) Re-validate movement fields inside transaction for a clean array
                $movementValidated = Validator::make(
                    $request->all(),
                    $this->productValidation->createExternalPurchaseMovementRules()
                )->validate();

                // 3) Create initial movement (EXTERNAL_PURCHASED / IN / COMPLETED)
                $movement = $this->productMovementService->createExternalPurchaseMovement(
                    $product,
                    $movementValidated,
                    $userId
                );

                // 4) Upload and store product images
                ProductHelper::handleImageUpload($request, $product);

                $freshProduct = ProductHelper::freshProduct($product);

                $this->auditLoggerService->logChange(
                    'product.create.external',
                    Product::class,
                    (int) $product->id,
                    [],
                    [
                        'product' => $this->auditLoggerService->snapshotModel($freshProduct),
                        'movement' => $this->auditLoggerService->snapshotModel($movement),
                    ],
                    $userId,
                    ['context' => 'product_service']
                );

                return ResponseHelper::success(['product' => $freshProduct], 'Product created successfully', 201);
            });

            return $result;
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function updateExternalPurchasedProduct (Request $request, $productId){

        try {
            $product = Product::findOrFail($productId);
            // Find the first EXTERNAL_PURCHASED movement for this product (initial purchase movement)
            $movement = ProductMovement::where('product_id', $product->id)
                ->where('movement_type', \App\Enums\ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value)
                ->orderBy('movement_date', 'asc')
                ->firstOrFail();

            // Validate incoming request body for external purchase movement update
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
                    'Invalid product type for external purchase update',
                    422,
                    'Product must be an externally purchased product to update this movement.'
                );
            }

            // Enforce defaults for this update
            $request->merge([
                'product_id' => $product->id,
                'direction' => \App\Enums\StockDirectionEnum::IN->value,
                'movement_type' => \App\Enums\ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value,
                'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
                'product_status' => $request->input(
                    'product_status',
                    is_object($movement->product_status) ? $movement->product_status->value : (string) $movement->product_status
                ),
            ]);

            // Only accept USD unit price + USD->Riel exchange rate as inputs; compute everything else from quantity.
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

            // If movement is sold, quantity becomes immutable. Other fields remain updatable.
            if ($movement->is_sold === true) {
                $incomingQty = $request->input('quantity');
                $currentQty = (float) $movement->quantity;

                if ($incomingQty !== null && $incomingQty !== '' && (float) $incomingQty !== $currentQty) {
                    return ResponseHelper::error(
                        'Cannot update sold movement quantity',
                        422,
                        'The first product movement has been sold. Quantity data cannot be updated to avoid data inconsistency.'
                    );
                }

                $request->merge(['quantity' => $currentQty]);
            }

            // Ensure selling-side fields exist (derive from last movement if present)
            $lastMovement = ProductMovement::where('product_id', $product->id)
                ->orderBy('movement_date', 'desc')
                ->first();

            $request->merge([
                // Keep incoming financial values from request; fallback only when missing.
                'selling_unit_price_in_usd' => $request->input(
                    'selling_unit_price_in_usd',
                    $lastMovement->selling_unit_price_in_usd ?? 0
                ),
                'selling_exchange_rate_from_usd_to_riel' => $request->input(
                    'selling_exchange_rate_from_usd_to_riel',
                    $lastMovement->selling_exchange_rate_from_usd_to_riel ?? 0
                ),
            ]);

            CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

            $validated = Validator::make($request->all(), $rules)->validate();

            $effectiveBaseUomId = (int) ($request->input('base_uom_id') ?: $product->base_uom_id);
            UomQuantityGuard::assertQuantityByUomId(
                $validated['quantity'] ?? null,
                $effectiveBaseUomId,
                'quantity'
            );

            // Preserve created_by from original movement if available and ensure last_updated_by exists
            $validated['created_by'] = $validated['created_by'] ?? $movement->created_by ?? $this->getCurrentUserHelper->getUserId();
            $validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

            // Validate optional product-level fields and merge into validated payload.
            // Prefer a dedicated updateProductRules() on ProductValidation if available.
            if (method_exists($this->productValidation, 'updateProductRules')) {
                $productRules = $this->productValidation->updateProductRules($request);
            } else {
                // Fallback rules: allow same fields as create but all optional for updates
                $productRules = [
                    'product_name' => 'sometimes|string|max:255',
                    'barcode' => 'sometimes|nullable|string|max:255',
                    'product_description' => 'sometimes|nullable|string',
                    'product_category_id' => 'sometimes|nullable|exists:product_categories,id',
                    'base_uom_id' => 'sometimes|nullable|exists:unit_of_measurements,id',
                    'supplier_id' => 'sometimes|nullable|exists:suppliers,id',
                    'warehouse_id' => 'sometimes|nullable|exists:warehouses,id',
                ];
            }

            $productValidated = Validator::make($request->all(), $productRules)->validate();

            // Merge product validated fields into movement validated payload so they persist together
            $validated = array_merge($validated, $productValidated);

            $oldSnapshot = [
                'product' => $this->auditLoggerService->snapshotModel($product),
                'movement' => $this->auditLoggerService->snapshotModel($movement),
            ];

            $movement = DB::transaction(function () use ($validated, $movement, $product) {
                // Update product-level fields if provided in the request
                $productData = [];
                if (array_key_exists('product_name', $validated)) {
                    $productData['product_name'] = $validated['product_name'];
                }
                if (array_key_exists('barcode', $validated)) {
                    $productData['barcode'] = $validated['barcode'];
                }
                if (array_key_exists('product_description', $validated)) {
                    $productData['product_description'] = $validated['product_description'];
                }
                if (array_key_exists('product_category_id', $validated)) {
                    $productData['product_category_id'] = $validated['product_category_id'];
                }
                if (array_key_exists('base_uom_id', $validated)) {
                    $productData['base_uom_id'] = $validated['base_uom_id'];
                }
                if (array_key_exists('supplier_id', $validated)) {
                    $productData['supplier_id'] = $validated['supplier_id'];
                }
                if (array_key_exists('warehouse_id', $validated)) {
                    $productData['warehouse_id'] = $validated['warehouse_id'];
                }
                if (array_key_exists('sale_method', $validated)) {
                    $productData['sale_method'] = $validated['sale_method'];
                }

                if (!empty($productData)) {
                    $product->update($productData);
                }

                // Update product-level fields if provided in the request
                $productData = [];
                if (array_key_exists('product_name', $validated)) {
                    $productData['product_name'] = $validated['product_name'];
                }
                if (array_key_exists('barcode', $validated)) {
                    $productData['barcode'] = $validated['barcode'];
                }
                if (array_key_exists('product_description', $validated)) {
                    $productData['product_description'] = $validated['product_description'];
                }
                if (array_key_exists('product_category_id', $validated)) {
                    $productData['product_category_id'] = $validated['product_category_id'];
                }
                if (array_key_exists('base_uom_id', $validated)) {
                    $productData['base_uom_id'] = $validated['base_uom_id'];
                }
                if (array_key_exists('supplier_id', $validated)) {
                    $productData['supplier_id'] = $validated['supplier_id'];
                }
                if (array_key_exists('warehouse_id', $validated)) {
                    $productData['warehouse_id'] = $validated['warehouse_id'];
                }
                if (array_key_exists('sale_method', $validated)) {
                    $productData['sale_method'] = $validated['sale_method'];
                }

                if (!empty($productData)) {
                    $product->update($productData);
                }

                $movement->update([
                    'quantity' => $validated['quantity'],
                    'product_status' => $validated['product_status'] ?? (is_object($movement->product_status) ? $movement->product_status->value : (string) $movement->product_status),
                    'direction' => \App\Enums\StockDirectionEnum::IN->value,
                    'movement_type' => \App\Enums\ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value,
                    'movement_date' => $validated['movement_date'],
                    'note' => $validated['note'] ?? null,

                    // Purchase pricing (derived by CurrencyPricingHelper)
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

            $newSnapshot = [
                'product' => $this->auditLoggerService->snapshotModel($product->fresh()),
                'movement' => $this->auditLoggerService->snapshotModel($movement),
            ];

            $this->auditLoggerService->logDiff(
                'product.update.external',
                Product::class,
                (int) $product->id,
                $oldSnapshot,
                $newSnapshot,
                (int) ($validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId()),
                ['context' => 'product_service']
            );

            return ResponseHelper::success($movement, 'Product updated successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

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

            // Normalize BOM to per-unit contract before validating.
            $request->merge([
                'raw_materials' => $this->manufacturingService->normalizeBomItems(
                    $request->input('raw_materials', [])
                ),
            ]);

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

            UomQuantityGuard::assertQuantityByUomId(
                $request->input('quantity'),
                (int) $request->input('base_uom_id'),
                'quantity'
            );
            UomQuantityGuard::assertBomQuantities($request->input('raw_materials', []));

            // Check raw material availability before opening the transaction.
            // Availability uses net stock (SUM(IN) - SUM(OUT)) without movement-date filtering.
            $bomItems = $request->input('raw_materials', []);
            $productionQuantity = (float) $request->input('quantity', 0);
            $consumptionPlan = $this->manufacturingService->buildConsumptionPlan($bomItems, $productionQuantity);
            $shortfalls = $this->manufacturingService->validateSufficientStockForPlan($consumptionPlan);

            if (!empty($shortfalls)) {
                return ResponseHelper::error(
                    'Insufficient raw material stock',
                    422,
                    $shortfalls
                );
            }

            $result = DB::transaction(function () use ($request, $bomItems, $consumptionPlan) {
                $userId       = $this->getCurrentUserHelper->getUserId();
                $movementDate = $request->input('movement_date', now()->toDateTimeString());

                // 1) Create product record
                $product = $this->productHelper->createProductRecord($request, $this->productValidation);

                // 2) Persist BOM pivot records
                $this->manufacturingService->replaceProductBom($product->id, $bomItems);

                // 3) Re-validate movement fields inside transaction for a clean array
                $movementValidated = Validator::make(
                    $request->all(),
                    $this->productValidation->createInternalManufacturingMovementRules()
                )->validate();

                // 4) Create initial movement (INTERNAL_PRODUCED / IN / COMPLETED)
                $movement = $this->productMovementService->createInternalProductionMovement(
                    $product,
                    $movementValidated,
                    $userId
                );

                // 5) Deduct raw material stock respecting FIFO/LIFO
                $referenceToken = $this->productHelper->buildReorderReferenceToken((int) $movement->id);
                $this->manufacturingService->deductStockForPlan(
                    $consumptionPlan,
                    $product->id,
                    $userId,
                    $movementDate,
                    $referenceToken
                );

                // 6) Upload and store product images
                ProductHelper::handleImageUpload($request, $product);

                $freshProduct = ProductHelper::freshProduct($product);
                $freshBom = $this->manufacturingService->getProductBom($product->id);

                $this->auditLoggerService->logChange(
                    'product.create.internal',
                    Product::class,
                    (int) $product->id,
                    [],
                    [
                        'product' => $this->auditLoggerService->snapshotModel($freshProduct),
                        'movement' => $this->auditLoggerService->snapshotModel($movement),
                        'bom' => $freshBom,
                    ],
                    $userId,
                    ['context' => 'product_service']
                );

                return ResponseHelper::success([
                    'product' => $freshProduct,
                    'materials' => $this->manufacturingService->extractMaterialsSummary($consumptionPlan),
                ], 'Product created successfully', 201);
            });

            return $result;
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function updateInternalManufacturedProduct (Request $request, $productId){
        try {
            $product = Product::findOrFail($productId);
            // Find the first INTERNAL_PRODUCED movement for this product (initial produced movement)
            $movement = ProductMovement::where('product_id', $product->id)
                ->where('movement_type', \App\Enums\ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value)
                ->orderBy('movement_date', 'asc')
                ->firstOrFail();

            $currentBom = $this->manufacturingService->getProductBom((int) $product->id);

            // Default update payload to existing product BOM if BOM omitted
            $requestBomItems = $request->input('raw_materials', []);
            if (empty($requestBomItems)) {
                $request->merge([
                    'raw_materials' => $currentBom,
                ]);
            }

            // Normalize BOM to per-unit contract before validating.
            $request->merge([
                'raw_materials' => $this->manufacturingService->normalizeBomItems(
                    $request->input('raw_materials', [])
                ),
            ]);

            // Validate incoming request body for internal manufacturing movement update
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
                    'Invalid product type for internal manufacturing update',
                    422,
                    'Product must be an internally produced product to update this movement.'
                );
            }

            // Enforce defaults for this update
            $request->merge([
                'product_id' => $product->id,
                'direction' => \App\Enums\StockDirectionEnum::IN->value,
                'movement_type' => \App\Enums\ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value,
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

            $isSoldInternalMovement = ($movement->is_sold === true);

            // If movement is sold, quantity becomes immutable. Other fields remain updatable.
            if ($isSoldInternalMovement) {
                $incomingQty = $request->input('quantity');
                $currentQty = (float) $movement->quantity;
                $incomingBomItems = $request->input('raw_materials', []);

                if ($incomingQty !== null && $incomingQty !== '' && (float) $incomingQty !== $currentQty) {
                    return ResponseHelper::error(
                        'Cannot update sold movement quantity',
                        422,
                        'The first product movement has been sold. Quantity data cannot be updated to avoid data inconsistency.'
                    );
                }

                if (!empty($incomingBomItems) && $this->manufacturingService->isDifferentBom($incomingBomItems, $currentBom)) {
                    return ResponseHelper::error(
                        'Cannot update sold movement BOM',
                        422,
                        'The first product movement has been sold. BOM data cannot be updated to avoid production line and BOM inconsistency.'
                    );
                }

                $request->merge([
                    'quantity' => $currentQty,
                    'raw_materials' => $currentBom,
                ]);
            }

            // Ensure selling-side fields exist (derive from last movement if present)
            $lastMovement = ProductMovement::where('product_id', $product->id)
                ->orderBy('movement_date', 'desc')
                ->first();

            $request->merge([
                // Keep incoming financial values from request; fallback only when missing.
                'selling_unit_price_in_usd' => $request->input(
                    'selling_unit_price_in_usd',
                    $lastMovement->selling_unit_price_in_usd ?? 0
                ),
                'selling_exchange_rate_from_usd_to_riel' => $request->input(
                    'selling_exchange_rate_from_usd_to_riel',
                    $lastMovement->selling_exchange_rate_from_usd_to_riel ?? 0
                ),
            ]);

            CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

            $validated = Validator::make($request->all(), $rules)->validate();

            $effectiveBaseUomId = (int) ($request->input('base_uom_id') ?: $product->base_uom_id);
            UomQuantityGuard::assertQuantityByUomId(
                $validated['quantity'] ?? null,
                $effectiveBaseUomId,
                'quantity'
            );
            UomQuantityGuard::assertBomQuantities($validated['raw_materials'] ?? []);

            // Preserve created_by from original movement if available and ensure last_updated_by exists
            $validated['created_by'] = $validated['created_by'] ?? $movement->created_by ?? $this->getCurrentUserHelper->getUserId();
            $validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

            // Validate optional product-level fields and merge into validated payload.
            if (method_exists($this->productValidation, 'updateProductRules')) {
                $productRules = $this->productValidation->updateProductRules($request);
            } else {
                $productRules = [
                    'product_name' => 'sometimes|string|max:255',
                    'barcode' => 'sometimes|nullable|string|max:255',
                    'product_description' => 'sometimes|nullable|string',
                    'product_category_id' => 'sometimes|nullable|exists:product_categories,id',
                    'base_uom_id' => 'sometimes|nullable|exists:unit_of_measurements,id',
                    'supplier_id' => 'sometimes|nullable|exists:suppliers,id',
                    'warehouse_id' => 'sometimes|nullable|exists:warehouses,id',
                ];
            }

            $productValidated = Validator::make($request->all(), $productRules)->validate();
            $validated = array_merge($validated, $productValidated);

            $oldSnapshot = [
                'product' => $this->auditLoggerService->snapshotModel($product),
                'movement' => $this->auditLoggerService->snapshotModel($movement),
                'bom' => $this->manufacturingService->getProductBom((int) $product->id),
            ];

            $bomItems = $validated['raw_materials'] ?? [];

            DB::beginTransaction();
            try {
                $referenceToken = $this->productHelper->buildReorderReferenceToken((int) $movement->id);
                $consumptionPlan = [];

                // For sold internal movement:
                // - quantity is already locked
                // - BOM is already locked
                // - do not touch historical raw-material deductions
                // This keeps production-line history consistent and still allows
                // product metadata / pricing updates.
                if (!$isSoldInternalMovement) {
                    $deletedRawMaterialIds = $this->manufacturingService->deleteConsumptionMovementsByToken($referenceToken);
                    $legacyRawIds = $this->manufacturingService->deleteLegacyConsumptionMovementsForProduct((int) $product->id);

                    $existingBomRawMaterialIds = collect($currentBom)
                        ->pluck('raw_material_id')
                        ->map(fn ($id) => (int) $id)
                        ->values()
                        ->all();

                    $incomingBomRawMaterialIds = collect($bomItems)
                        ->pluck('raw_material_id')
                        ->map(fn ($id) => (int) $id)
                        ->values()
                        ->all();

                    $rebuildIds = array_values(array_unique(array_merge(
                        $deletedRawMaterialIds,
                        $legacyRawIds,
                        $existingBomRawMaterialIds,
                        $incomingBomRawMaterialIds
                    )));
                    $this->manufacturingService->rebuildInUsedFlags($rebuildIds);

                    $consumptionPlan = $this->manufacturingService->buildConsumptionPlan(
                        $bomItems,
                        (float) ($validated['quantity'] ?? 0)
                    );
                    $shortfalls = $this->manufacturingService->validateSufficientStockForPlan($consumptionPlan);
                    if (!empty($shortfalls)) {
                        DB::rollBack();
                        return ResponseHelper::error('Insufficient raw material stock', 422, $shortfalls);
                    }

                    $this->manufacturingService->replaceProductBom((int) $product->id, $bomItems);
                }

                // Update product-level fields when provided in internal update flow
                $productData = [];
                if (array_key_exists('product_name', $validated)) {
                    $productData['product_name'] = $validated['product_name'];
                }
                if (array_key_exists('barcode', $validated)) {
                    $productData['barcode'] = $validated['barcode'];
                }
                if (array_key_exists('product_description', $validated)) {
                    $productData['product_description'] = $validated['product_description'];
                }
                if (array_key_exists('product_category_id', $validated)) {
                    $productData['product_category_id'] = $validated['product_category_id'];
                }
                if (array_key_exists('base_uom_id', $validated)) {
                    $productData['base_uom_id'] = $validated['base_uom_id'];
                }
                if (array_key_exists('supplier_id', $validated)) {
                    $productData['supplier_id'] = $validated['supplier_id'];
                }
                if (array_key_exists('warehouse_id', $validated)) {
                    $productData['warehouse_id'] = $validated['warehouse_id'];
                }
                if (array_key_exists('sale_method', $validated)) {
                    $productData['sale_method'] = $validated['sale_method'];
                }

                if (!empty($productData)) {
                    $product->update($productData);
                }

                if (!$isSoldInternalMovement) {
                    $this->manufacturingService->deductStockForPlan(
                        $consumptionPlan,
                        $product->id,
                        (int) $validated['last_updated_by'],
                        $validated['movement_date'],
                        $referenceToken
                    );
                }

                $movement->update([
                    'quantity' => $validated['quantity'],
                    'product_status' => $validated['product_status'] ?? (is_object($movement->product_status) ? $movement->product_status->value : (string) $movement->product_status),
                    'direction' => \App\Enums\StockDirectionEnum::IN->value,
                    'movement_type' => \App\Enums\ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value,
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

                DB::commit();

                $movement = $movement->fresh();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

            $newSnapshot = [
                'product' => $this->auditLoggerService->snapshotModel($product->fresh()),
                'movement' => $this->auditLoggerService->snapshotModel($movement),
                'bom' => $this->manufacturingService->getProductBom((int) $product->id),
            ];

            $this->auditLoggerService->logDiff(
                'product.update.internal',
                Product::class,
                (int) $product->id,
                $oldSnapshot,
                $newSnapshot,
                (int) ($validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId()),
                ['context' => 'product_service']
            );

            $materials = $this->manufacturingService->extractMaterialsSummary(
                $this->manufacturingService->buildConsumptionPlan(
                    $this->manufacturingService->getProductBom((int) $product->id),
                    (float) ($movement->quantity ?? 0)
                )
            );

            return ResponseHelper::success([
                'movement' => $movement,
                'materials' => $materials,
            ], 'Product updated successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }


    // Delete Product - to be implemented with soft deletes and cascade to movements, images, BOM, etc.
    public function deleteProduct($productId)
    {
        try {
            $product = Product::findOrFail($productId);
            $oldSnapshot = $this->auditLoggerService->snapshotModel($product);
            $userId = $this->getCurrentUserHelper->getUserId();
            $product->delete();

            $this->auditLoggerService->logChange(
                'product.delete',
                Product::class,
                (int) $product->id,
                $oldSnapshot,
                [],
                $userId,
                ['context' => 'product_service']
            );

            return ResponseHelper::success(null, 'Product deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    // recovery of soft-deleted product
    public function restoreProduct($productId){
        try {
            $product = Product::withTrashed()->findOrFail($productId);
            if ($product->trashed()) {
                $oldSnapshot = $this->auditLoggerService->snapshotModel($product);
                $product->restore();

                $this->auditLoggerService->logChange(
                    'product.restore',
                    Product::class,
                    (int) $product->id,
                    $oldSnapshot,
                    $this->auditLoggerService->snapshotModel($product->fresh()),
                    $this->getCurrentUserHelper->getUserId(),
                    ['context' => 'product_service']
                );

                return ResponseHelper::success(null, 'Product restored successfully');
            } else {
                return ResponseHelper::error('Product is not deleted', 400);
            }
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    // Image upload and fresh-loading helpers moved to ProductHelper.
}
