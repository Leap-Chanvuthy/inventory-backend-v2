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
    protected ProductStockAllocationService   $productStockAllocationService;

    public function __construct(
        ProductValidation                $productValidation,
        ProductQueryBuilder              $productQueryBuilder,
        ProductMovementService           $productMovementService,
        ManufacturingService             $manufacturingService,
        GetCurrentUserHelper             $getCurrentUserHelper,
        ProductPnLService                $productPnLService,
        ProductHelper                    $productHelper,
        AuditLoggerService               $auditLoggerService,
        ProductStockAllocationService    $productStockAllocationService
    ) {
        $this->productValidation       = $productValidation;
        $this->productQueryBuilder     = $productQueryBuilder;
        $this->productMovementService  = $productMovementService;
        $this->manufacturingService    = $manufacturingService;
        $this->getCurrentUserHelper    = $getCurrentUserHelper;
        $this->productPnLService       = $productPnLService;
        $this->productHelper          = $productHelper;
        $this->auditLoggerService      = $auditLoggerService;
        $this->productStockAllocationService = $productStockAllocationService;
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

    public function getAllProductStockLots(Request $request, $productId)
    {
        try {
            Product::findOrFail($productId);
            return $this->productQueryBuilder->productStockLotBuilder($request, (int) $productId);
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

            // Total count of movements by movement_type
            $totalCountByMovementType = \App\Models\ProductMovement::where('product_id', $product->id)
                ->select('movement_type', DB::raw('COUNT(*) as total'))
                ->groupBy('movement_type')
                ->pluck('total', 'movement_type');

            // Operational stock comes from remaining quantity on stock-IN lots.
            $currentQtyInStock = (float) ProductMovement::where('product_id', $product->id)
                ->where('direction', \App\Enums\StockDirectionEnum::IN->value)
                ->sum('remaining_quantity');

            // Ledger stock (IN - OUT) is still returned for audit/debug visibility.
            $ledgerQtyInStock = (float) ProductMovement::where('product_id', $product->id)
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

            $pricingReferenceLot = $this->resolvePricingReferenceLot($product);

            $isInitialMovementSold = $initialMovement
                ? $initialMovement->sourceAllocations()->exists()
                : false;

            return ResponseHelper::success([
                'is_sold' => $isInitialMovementSold,
                'allow_bom_update' => !$isInitialMovementSold,
                'product' => $product,
                'initial_movement' => $initialMovement,
                'pricing_reference_lot' => $this->mapStockLot($pricingReferenceLot),
                'current_qty_in_stock' => $currentQtyInStock,
                'available_qty_in_stock' => $currentQtyInStock,
                'ledger_qty_in_stock' => $ledgerQtyInStock,
                'product_stock_status' => $productStockStatus,
                'total_count_by_movement_type' => $totalCountByMovementType,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function previewSaleAllocation(Request $request, int $productId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quantity' => 'required|numeric|min:0.0001',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $product = Product::query()->findOrFail($productId);
            $quantity = (float) $request->input('quantity');

            UomQuantityGuard::assertQuantityByUomId(
                $quantity,
                (int) $product->base_uom_id,
                'quantity'
            );

            $preview = $this->productStockAllocationService->previewProductSaleAllocation($product, $quantity);

            return ResponseHelper::success($preview, 'Sale allocation preview generated successfully');
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getProductPnLDetail(int $productId)
    {
        try {
            Product::query()->findOrFail($productId);
            $detail = $this->productPnLService->getDetailedProductPnL($productId);
            if (is_array($detail) && array_key_exists('error', $detail)) {
                return ResponseHelper::error((string) ($detail['error'] ?? 'Unable to build detailed P&L'), 500);
            }
            return ResponseHelper::success($detail, 'Product detailed P&L retrieved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    private function resolvePricingReferenceLot(Product $product): ?ProductMovement
    {
        $saleMethod = strtoupper((string) ($product->sale_method instanceof \BackedEnum
            ? $product->sale_method->value
            : ($product->sale_method ?? 'FIFO')));
        $orderDirection = $saleMethod === \App\Enums\SaleMethodEnum::LIFO->value ? 'desc' : 'asc';

        $baseQuery = ProductMovement::query()
            ->where('product_id', $product->id)
            ->where('direction', \App\Enums\StockDirectionEnum::IN->value);

        $availableLot = (clone $baseQuery)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('movement_date', $orderDirection)
            ->orderBy('id', $orderDirection)
            ->first();

        if ($availableLot) {
            return $availableLot;
        }

        return (clone $baseQuery)
            ->orderBy('movement_date', $orderDirection)
            ->orderBy('id', $orderDirection)
            ->first();
    }

    private function mapStockLot(?ProductMovement $lot): ?array
    {
        if (!$lot) {
            return null;
        }

        $quantity = (float) $lot->quantity;
        $remaining = (float) $lot->remaining_quantity;

        return [
            'id' => (int) $lot->id,
            'movement_type' => $lot->movement_type instanceof \BackedEnum ? $lot->movement_type->value : (string) $lot->movement_type,
            'product_status' => $lot->product_status instanceof \BackedEnum ? $lot->product_status->value : $lot->product_status,
            'quantity' => $quantity,
            'remaining_quantity' => $remaining,
            'allocated_quantity' => max(0, round($quantity - $remaining, 4)),
            'lot_status' => $remaining <= 0
                ? 'CONSUMED'
                : ($remaining < $quantity ? 'PARTIALLY_CONSUMED' : 'AVAILABLE'),
            'selling_unit_price_in_usd' => (float) ($lot->selling_unit_price_in_usd ?? 0),
            'selling_unit_price_in_riel' => (float) ($lot->selling_unit_price_in_riel ?? 0),
            'purchase_unit_price_in_usd' => (float) ($lot->purchase_unit_price_in_usd ?? 0),
            'purchase_unit_price_in_riel' => (float) ($lot->purchase_unit_price_in_riel ?? 0),
            'movement_date' => optional($lot->movement_date)->toDateTimeString(),
        ];
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

            $isAllocatedMovement = $movement->sourceAllocations()->exists();

            if ($isAllocatedMovement) {
                $lockedNumericFields = [
                    'quantity',
                    'purchase_unit_price_in_usd',
                    'purchase_total_price_in_usd',
                    'purchase_unit_price_in_riel',
                    'purchase_total_price_in_riel',
                    'exchange_rate_from_usd_to_riel',
                    'exchange_rate_from_riel_to_usd',
                    'selling_unit_price_in_usd',
                    'selling_unit_price_in_riel',
                    'selling_exchange_rate_from_usd_to_riel',
                    'selling_exchange_rate_from_riel_to_usd',
                ];

                foreach ($lockedNumericFields as $lockedField) {
                    if ($this->hasNumericFieldChanged($request, $lockedField, $movement->{$lockedField} ?? null)) {
                        return ResponseHelper::error(
                            'Cannot update allocated stock lot',
                            422,
                            'This stock lot has already been used in a sale. Quantity, price, movement date, and BOM cannot be changed. Use an adjustment or reorder movement instead.'
                        );
                    }
                }

                if ($this->hasDateFieldChanged($request, 'movement_date', $movement->movement_date)) {
                    return ResponseHelper::error(
                        'Cannot update allocated stock lot',
                        422,
                        'This stock lot has already been used in a sale. Quantity, price, movement date, and BOM cannot be changed. Use an adjustment or reorder movement instead.'
                    );
                }

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

                $metadataValidated = Validator::make(
                    $request->all(),
                    array_merge($productRules, ['note' => 'sometimes|nullable|string'])
                )->validate();

                $lastUpdatedBy = $this->getCurrentUserHelper->getUserId();

                $oldSnapshot = [
                    'product' => $this->auditLoggerService->snapshotModel($product),
                    'movement' => $this->auditLoggerService->snapshotModel($movement),
                ];

                $movement = DB::transaction(function () use ($metadataValidated, $product, $movement, $lastUpdatedBy) {
                    $productData = $this->extractUpdatableProductData($metadataValidated);
                    if (!empty($productData)) {
                        $product->update($productData);
                    }

                    if (array_key_exists('note', $metadataValidated)) {
                        $movement->update([
                            'note' => $metadataValidated['note'],
                            'last_updated_by' => $lastUpdatedBy,
                        ]);
                    }

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
                    $lastUpdatedBy,
                    ['context' => 'product_service']
                );

                return ResponseHelper::success($movement, 'Product updated successfully', 201);
            }

            // Validate incoming request body for external purchase movement update
            $rules = $this->productValidation->createExternalPurchaseMovementRules();
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
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

                $currentQty = (float) $movement->quantity;
                $currentRemaining = (float) $movement->remaining_quantity;
                $alreadyAllocated = max(0, round($currentQty - $currentRemaining, 4));
                $nextQty = (float) $validated['quantity'];

                $movement->update([
                    'quantity' => $nextQty,
                    'remaining_quantity' => max(0, round($nextQty - $alreadyAllocated, 4)),
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

            $isSoldInternalMovement = $movement->sourceAllocations()->exists();

            if ($isSoldInternalMovement) {
                $lockedNumericFields = [
                    'quantity',
                    'purchase_unit_price_in_usd',
                    'purchase_total_price_in_usd',
                    'purchase_unit_price_in_riel',
                    'purchase_total_price_in_riel',
                    'exchange_rate_from_usd_to_riel',
                    'exchange_rate_from_riel_to_usd',
                    'selling_unit_price_in_usd',
                    'selling_unit_price_in_riel',
                    'selling_exchange_rate_from_usd_to_riel',
                    'selling_exchange_rate_from_riel_to_usd',
                ];

                foreach ($lockedNumericFields as $lockedField) {
                    if ($this->hasNumericFieldChanged($request, $lockedField, $movement->{$lockedField} ?? null)) {
                        return ResponseHelper::error(
                            'Cannot update allocated stock lot',
                            422,
                            'This stock lot has already been used in a sale. Quantity, price, movement date, and BOM cannot be changed. Use an adjustment or reorder movement instead.'
                        );
                    }
                }

                if ($this->hasDateFieldChanged($request, 'movement_date', $movement->movement_date)) {
                    return ResponseHelper::error(
                        'Cannot update allocated stock lot',
                        422,
                        'This stock lot has already been used in a sale. Quantity, price, movement date, and BOM cannot be changed. Use an adjustment or reorder movement instead.'
                    );
                }

                if ($request->filled('raw_materials')) {
                    $incomingBom = $this->manufacturingService->normalizeBomItems(
                        $request->input('raw_materials', [])
                    );
                    if ($this->hasBomChanged($currentBom, $incomingBom)) {
                        return ResponseHelper::error(
                            'Cannot update allocated stock lot',
                            422,
                            'This stock lot has already been used in a sale. Quantity, price, movement date, and BOM cannot be changed. Use an adjustment or reorder movement instead.'
                        );
                    }
                }

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

                $metadataValidated = Validator::make(
                    $request->all(),
                    array_merge($productRules, ['note' => 'sometimes|nullable|string'])
                )->validate();

                $lastUpdatedBy = $this->getCurrentUserHelper->getUserId();
                $oldSnapshot = [
                    'product' => $this->auditLoggerService->snapshotModel($product),
                    'movement' => $this->auditLoggerService->snapshotModel($movement),
                    'bom' => $this->manufacturingService->getProductBom((int) $product->id),
                ];

                DB::beginTransaction();
                try {
                    $productData = $this->extractUpdatableProductData($metadataValidated);
                    if (!empty($productData)) {
                        $product->update($productData);
                    }

                    if (array_key_exists('note', $metadataValidated)) {
                        $movement->update([
                            'note' => $metadataValidated['note'],
                            'last_updated_by' => $lastUpdatedBy,
                        ]);
                    }

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
                    $lastUpdatedBy,
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
            }

            // Validate incoming request body for internal manufacturing movement update
            $rules = $this->productValidation->createInternalManufacturingMovementRules();
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
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
                    'remaining_quantity' => $validated['quantity'],
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


    private function extractUpdatableProductData(array $validated): array
    {
        $data = [];

        if (array_key_exists('product_name', $validated)) {
            $data['product_name'] = $validated['product_name'];
        }
        if (array_key_exists('barcode', $validated)) {
            $data['barcode'] = $validated['barcode'];
        }
        if (array_key_exists('product_description', $validated)) {
            $data['product_description'] = $validated['product_description'];
        }
        if (array_key_exists('product_category_id', $validated)) {
            $data['product_category_id'] = $validated['product_category_id'];
        }
        if (array_key_exists('base_uom_id', $validated)) {
            $data['base_uom_id'] = $validated['base_uom_id'];
        }
        if (array_key_exists('supplier_id', $validated)) {
            $data['supplier_id'] = $validated['supplier_id'];
        }
        if (array_key_exists('warehouse_id', $validated)) {
            $data['warehouse_id'] = $validated['warehouse_id'];
        }
        if (array_key_exists('sale_method', $validated)) {
            $data['sale_method'] = $validated['sale_method'];
        }

        return $data;
    }

    private function hasNumericFieldChanged(Request $request, string $field, mixed $currentValue, float $epsilon = 0.000001): bool
    {
        if (!$request->has($field)) {
            return false;
        }

        $incoming = $request->input($field);

        if ($incoming === null && $currentValue === null) {
            return false;
        }

        if (!is_numeric($incoming) || !is_numeric($currentValue)) {
            return (string) $incoming !== (string) $currentValue;
        }

        return abs((float) $incoming - (float) $currentValue) > $epsilon;
    }

    private function hasDateFieldChanged(Request $request, string $field, mixed $currentValue): bool
    {
        if (!$request->filled($field)) {
            return false;
        }

        $incomingTs = strtotime((string) $request->input($field));
        $currentTs = strtotime((string) $currentValue);

        if ($incomingTs === false || $currentTs === false) {
            return (string) $request->input($field) !== (string) $currentValue;
        }

        return date('Y-m-d', $incomingTs) !== date('Y-m-d', $currentTs);
    }

    private function hasBomChanged(array $currentBom, array $incomingBom): bool
    {
        $normalize = static function (array $items): array {
            $normalized = collect($items)
                ->map(function ($item) {
                    return [
                        'raw_material_id' => (int) ($item['raw_material_id'] ?? 0),
                        'quantity_per_unit' => (float) ($item['quantity_per_unit'] ?? 0),
                        'scrap_percentage' => round((float) ($item['scrap_percentage'] ?? 0), 6),
                    ];
                })
                ->sortBy('raw_material_id')
                ->values()
                ->all();

            return $normalized;
        };

        return $normalize($currentBom) !== $normalize($incomingBom);
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
