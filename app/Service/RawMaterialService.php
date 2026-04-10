<?php

namespace App\Service;

use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Helpers\CurrencyPricingHelper;
use App\Helpers\FileUploadHelper;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Models\RMImage;
use App\Models\RMStockMovement;
use App\Models\RawMaterial;
use App\QueryBuilders\RawMaterialQueryBuilder;
use App\Validations\RMValidation;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RawMaterialService
{
    protected RMValidation $rmValidation;
    protected GetCurrentUserHelper $getCurrentUserHelper;
    protected RawMaterialQueryBuilder $rawMaterialQueryBuilder;
    protected AuditLoggerService $auditLoggerService;

    public function __construct(
        RMValidation $rmValidation,
        RawMaterialQueryBuilder $rawMaterialQueryBuilder,
        GetCurrentUserHelper $getCurrentUserHelper,
        AuditLoggerService $auditLoggerService
        )
    {
        $this -> rmValidation = $rmValidation;
        $this -> rawMaterialQueryBuilder = $rawMaterialQueryBuilder;
        $this -> getCurrentUserHelper = $getCurrentUserHelper;
        $this -> auditLoggerService = $auditLoggerService;
    }

    // Get all raw materials with filtering, sorting, and pagination
    public function getAllRawMaterials(Request $request)
    {
        try {
            return $this->rawMaterialQueryBuilder->rawMaterialBuilder($request); 
        }catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }


    // Get raw material by ID
    public function getRawMaterialById($id)
    {
        try {
            $rawMaterial = RawMaterial::with([
                'rm_category' => fn ($q) => $q->withTrashed(),
                'supplier' => fn ($q) => $q->withTrashed(),
                'warehouse' => fn ($q) => $q->withTrashed(),
                'uom' => fn ($q) => $q->withTrashed(),
                'rm_stock_movements.created_by',
                'rm_stock_movements.last_updated_by',
                'rm_images',
            ])->findOrFail($id);


            // Find total count of stock movement by type
            $totalCountByMovementType = RMStockMovement::where('raw_material_id', $rawMaterial -> id)
            ->select('movement_type', DB::raw('COUNT(*) as total'))
            ->groupBy('movement_type')
            ->pluck('total', 'movement_type');

            
                        
            // Calculate current quantity in stock
            $currentQtyInStock = 0;
            foreach ($rawMaterial->rm_stock_movements as $movement) {
                $qty = (float) ($movement->quantity ?? 0);
                $currentQtyInStock += ($movement->direction === 'OUT') ? (-$qty) : $qty;
            }

            // Find stock status
            $isExpired = false;
            $rawMaterialStatus = '';
            $effectiveExpiryDate = RMStockMovement::query()
                ->where('raw_material_id', $rawMaterial->id)
                ->where('movement_type', RawMaterialStockMovementTypeEnum::PURCHASE->value)
                ->value('expiry_date');

            if (!$effectiveExpiryDate) {
                $effectiveExpiryDate = RMStockMovement::query()
                    ->where('raw_material_id', $rawMaterial->id)
                    ->whereNotNull('expiry_date')
                    ->orderByDesc('movement_date')
                    ->value('expiry_date');
            }

            if ($effectiveExpiryDate) {
                $isExpired = Carbon::parse($effectiveExpiryDate)
                    ->startOfDay()
                    ->lte(now()->startOfDay());
            }

            if ($isExpired) {
                $rawMaterialStatus = 'EXPIRED';
            } elseif ($currentQtyInStock <= 0) {
                $rawMaterialStatus = 'OUT_OF_STOCK';
            } elseif ($currentQtyInStock <= (float) $rawMaterial->minimum_stock_level) {
                $rawMaterialStatus = 'LOW_STOCK';
            } else {
                $rawMaterialStatus = 'IN_STOCK';
            }

                
            if (!$rawMaterial) {
                return ResponseHelper::error('Raw Material not found', 404);
            }

            return ResponseHelper::success([
                'raw_material' => $rawMaterial,
                'current_qty_in_stock' => $currentQtyInStock,
                'raw_material_status' => $rawMaterialStatus,
                'total_count_by_movement_type' => $totalCountByMovementType,
            ],  'Raw Material retrieved successfully');
        } catch (Exception $e) {        
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }


    public function getAllDeletedRawMaterials(Request $request)
    {
        try {
            // Use the query builder's onlyTrashed flag so pagination and query builder
            // behavior remain correct (don't call ->withTrashed() on the paginator)
            return $this->rawMaterialQueryBuilder->rawMaterialBuilder($request, true);

        }catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    

    // Create a new raw material with initial stock movement (PURCHASE/IN) and optional images.
    public function createRawMaterial(Request $request)
    {
        try {
            // Defaults for create flow
            if (!$request->filled('movement_type')) {
                $request->merge(['movement_type' => 'PURCHASE']);
            }
            if (!$request->filled('direction')) {
                $request->merge(['direction' => 'IN']);
            }
            if (!$request->filled('movement_date')) {
                $request->merge(['movement_date' => now()->toDateTimeString()]);
            }

            // Always derive created_by / last_updated_by from the authenticated user
            $currentUserId = $this->getCurrentUserHelper->getUserId();
            $request->merge([
                'created_by'      => $currentUserId,
                'last_updated_by' => $currentUserId,
            ]);

            // Compute pricing fields (totals + currency conversions) before validation.
            CurrencyPricingHelper::fillRMPurchasingCurrencyFields($request);

            // Validate everything up-front and return ALL errors at once.
            $mergeErrors = function (array $base, array $incoming): array {
                foreach ($incoming as $field => $messages) {
                    $base[$field] = array_values(array_unique(array_merge($base[$field] ?? [], $messages)));
                }
                return $base;
            };

            $errors = [];

            // 1) Raw material main validation
            $rawMaterialRules = $this->rmValidation->CreateRMValidation($request);
            $rawMaterialValidator = Validator::make($request->all(), $rawMaterialRules);
            if ($rawMaterialValidator->fails()) {
                $errors = $mergeErrors($errors, $rawMaterialValidator->errors()->toArray());
            }

            // 2) Stock movement validation (raw_material_id comes from created raw material)
            $stockMovementRules = $this->rmValidation->CreateRMStockMovementValidation($request);
            unset($stockMovementRules['raw_material_id']);
            $stockMovementValidator = Validator::make($request->all(), $stockMovementRules);
            if ($stockMovementValidator->fails()) {
                $errors = $mergeErrors($errors, $stockMovementValidator->errors()->toArray());
            }

            // 3) Images validation (raw_material_id is optional here)
            $imageRules = $this->rmValidation->CreateRMImageValidation($request);
            $imageValidator = Validator::make($request->all(), $imageRules);
            if ($imageValidator->fails()) {
                $errors = $mergeErrors($errors, $imageValidator->errors()->toArray());
            }

            if (!empty($errors)) {
                return ResponseHelper::validation($errors, 'Validation Error');
            }

            return DB::transaction(function () use ($request, $currentUserId) {
                // 1) Create Raw Material
                $rawMaterialRules = $this->rmValidation->CreateRMValidation($request);
                $rawMaterialData = Validator::make($request->all(), $rawMaterialRules)->validate();

                $rawMaterial = RawMaterial::create([
                    'material_name'            => $rawMaterialData['material_name'],
                    'material_sku_code'        => $rawMaterialData['material_sku_code'],
                    'barcode'                  => $rawMaterialData['barcode'] ?? null,
                    'minimum_stock_level'      => $rawMaterialData['minimum_stock_level'],
                    'description'              => $rawMaterialData['description'] ?? null,
                    'raw_material_category_id' => $rawMaterialData['raw_material_category_id'],
                    'base_uom_id'              => $rawMaterialData['base_uom_id'],
                    'supplier_id'              => $rawMaterialData['supplier_id'] ?? null,
                    'warehouse_id'             => $rawMaterialData['warehouse_id'],
                    'production_method'        => $rawMaterialData['production_method'],
                ]);

                // 2) Create initial Stock Movement (PURCHASE / IN / now)
                $request->merge([
                    'raw_material_id' => $rawMaterial->id,
                    'direction' => 'IN',
                    'movement_type' => 'PURCHASE',
                    'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
                ]);

                CurrencyPricingHelper::fillRMPurchasingCurrencyFields($request);

                $stockMovementRules = $this->rmValidation->CreateRMStockMovementValidation($request);
                $stockMovementData = Validator::make($request->all(), $stockMovementRules)->validate();

                $movement = RMStockMovement::create([
                    'raw_material_id' => $rawMaterial->id,
                    'quantity' => $stockMovementData['quantity'],
                    'direction' => 'IN',
                    'movement_type' => 'PURCHASE',
                    'movement_date' => $stockMovementData['movement_date'],
                    'expiry_date' => $stockMovementData['expiry_date'],
                    'unit_price_in_usd' => $stockMovementData['unit_price_in_usd'],
                    'total_value_in_usd' => $stockMovementData['total_value_in_usd'],
                    'exchange_rate_from_usd_to_riel' => $stockMovementData['exchange_rate_from_usd_to_riel'],
                    'unit_price_in_riel' => $stockMovementData['unit_price_in_riel'],
                    'total_value_in_riel' => $stockMovementData['total_value_in_riel'],
                    'exchange_rate_from_riel_to_usd' => $stockMovementData['exchange_rate_from_riel_to_usd'],
                    'created_by' => $stockMovementData['created_by'],
                    'last_updated_by' => $stockMovementData['last_updated_by'],
                    'note' => $stockMovementData['note'] ?? null,
                ]);

                // 3) Upload images (max 4)
                $files = [];
                if ($request->hasFile('images')) {
                    $files = $request->file('images');
                } elseif ($request->hasFile('image')) {
                    $files = [$request->file('image')];
                }

                $images = [];
                if (!empty($files)) {
                    // Enforce max 4 images even if request bypasses validation somehow
                    $files = array_slice($files, 0, 4);

                    foreach ($files as $file) {
                        $imageUrl = FileUploadHelper::uploadSingle($file, 'raw-materials', null);
                        $images[] = RMImage::create([
                            'raw_material_id' => $rawMaterial->id,
                            'image' => $imageUrl,
                        ]);
                    }
                }

                $data = [
                    'raw_material' => $rawMaterial->fresh(
                        ['rm_category', 'supplier' => fn ($q) => $q->withTrashed() , 'warehouse'=> fn ($q) => $q->withTrashed(), 'uom' => fn ($q) => $q->withTrashed() , 'rm_stock_movements', 'rm_images']),
                ];

                $this->auditLoggerService->logChange(
                    'raw_material.create',
                    RawMaterial::class,
                    (int) $rawMaterial->id,
                    [],
                    [
                        'raw_material' => $this->auditLoggerService->snapshotModel($data['raw_material']),
                        'purchase_movement' => $this->auditLoggerService->snapshotModel($movement),
                        'images' => $data['raw_material']->rm_images?->map(fn ($img) => [
                            'id' => (int) $img->id,
                            'image' => $img->image,
                        ])->values()->all() ?? [],
                    ],
                    (int) $currentUserId,
                    ['context' => 'raw_material_service']
                );

                return ResponseHelper::success($data, 'Raw material created successfully', 201);
            });
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }


    // Update Raw Material
    public function updateRawMaterial($id, Request $request)
    {
        try {

            $rawMaterial = RawMaterial::query()->findOrFail($id);

            $purchaseMovement = RMStockMovement::query()
                ->where('raw_material_id', $rawMaterial->id)
                ->where('movement_type', RawMaterialStockMovementTypeEnum::PURCHASE->value)
                ->first();

            if (!$purchaseMovement) {
                return ResponseHelper::validation([
                    'movement_type' => ['PURCHASE stock movement not found for this raw material.'],
                ], 'Validation Error');
            }

            // Do not allow changing PURCHASE movement type.
            if ($request->has('movement_type') && $request->input('movement_type') !== RawMaterialStockMovementTypeEnum::PURCHASE->value) {
                return ResponseHelper::validation([
                    'movement_type' => ['PURCHASE movement type cannot be changed.'],
                ], 'Validation Error');
            }

            // Validate everything up-front and return ALL errors at once.
            $mergeErrors = function (array $base, array $incoming): array {
                foreach ($incoming as $field => $messages) {
                    $base[$field] = array_values(array_unique(array_merge($base[$field] ?? [], $messages)));
                }
                return $base;
            };

            $errors = [];

            // 1) Raw material validation
            $rawMaterialRules = $this->rmValidation->UpdateRMValidation($request, (int) $rawMaterial->id);
            $rawMaterialValidator = Validator::make($request->all(), $rawMaterialRules);
            if ($rawMaterialValidator->fails()) {
                $errors = $mergeErrors($errors, $rawMaterialValidator->errors()->toArray());
            }

            // 2) PURCHASE stock movement update validation (qty/unit price/exchange rate)
            // Enforce movement properties for PURCHASE.
            $request->merge([
                'raw_material_id' => $rawMaterial->id,
                'movement_type' => RawMaterialStockMovementTypeEnum::PURCHASE->value,
                'direction' => StockDirectionEnum::IN->value,
                'movement_date' => $request->input('movement_date', optional($purchaseMovement->movement_date)->toDateTimeString() ?? now()->toDateTimeString()),
                'expiry_date' => $request->input('expiry_date', optional($purchaseMovement->expiry_date)?->toDateString()),
            ]);

            CurrencyPricingHelper::fillRMPurchasingCurrencyFields($request);

            // Preserve original created_by; update last_updated_by to the acting user
            $request->merge([
                'created_by'      => $purchaseMovement->created_by,
                'last_updated_by' => $this->getCurrentUserHelper->getUserId(),
            ]);

            $purchaseRules = $this->rmValidation->CreateRMStockMovementValidation($request);
            $purchaseValidator = Validator::make($request->all(), $purchaseRules);
            if ($purchaseValidator->fails()) {
                $errors = $mergeErrors($errors, $purchaseValidator->errors()->toArray());
            }

            // 3) Images append validation (optional)
            $incomingFiles = [];
            if ($request->hasFile('images')) {
                $incomingFiles = $request->file('images');
            } elseif ($request->hasFile('image')) {
                $incomingFiles = [$request->file('image')];
            }

            if (!empty($incomingFiles)) {
                $imageRules = $this->rmValidation->CreateRMImageValidation($request);
                $imageValidator = Validator::make($request->all(), $imageRules);
                if ($imageValidator->fails()) {
                    $errors = $mergeErrors($errors, $imageValidator->errors()->toArray());
                }

                $existingImageCount = RMImage::query()
                    ->where('raw_material_id', $rawMaterial->id)
                    ->count();
                $incomingCount = is_array($incomingFiles) ? count($incomingFiles) : 1;

                if (($existingImageCount + $incomingCount) > 4) {
                    $errors = $mergeErrors($errors, [
                        'images' => ["You can upload at most 4 images total. Existing: {$existingImageCount}, incoming: {$incomingCount}."],
                    ]);
                }
            }

            if (!empty($errors)) {
                return ResponseHelper::validation($errors, 'Validation Error');
            }

            return DB::transaction(function () use ($request, $rawMaterial, $purchaseMovement, $incomingFiles) {
                $oldSnapshot = [
                    'raw_material' => $this->auditLoggerService->snapshotModel($rawMaterial),
                    'purchase_movement' => $this->auditLoggerService->snapshotModel($purchaseMovement),
                    'images' => RMImage::query()
                        ->where('raw_material_id', $rawMaterial->id)
                        ->orderBy('id')
                        ->get(['id', 'image'])
                        ->map(fn ($img) => [
                            'id' => (int) $img->id,
                            'image' => $img->image,
                        ])->values()->all(),
                ];

                $rawMaterialRules = $this->rmValidation->UpdateRMValidation($request, (int) $rawMaterial->id);
                $rawMaterialData = Validator::make($request->all(), $rawMaterialRules)->validate();

                // Recompute pricing after any field changes.
                $request->merge([
                    'raw_material_id' => $rawMaterial->id,
                    'movement_type' => RawMaterialStockMovementTypeEnum::PURCHASE->value,
                    'direction' => StockDirectionEnum::IN->value,
                ]);

                CurrencyPricingHelper::fillRMPurchasingCurrencyFields($request);

                $purchaseRules = $this->rmValidation->CreateRMStockMovementValidation($request);
                $purchaseData = Validator::make($request->all(), $purchaseRules)->validate();

                // Only block when user tries to change PURCHASE quantity after it's been used.
                // Allow updates to other Raw Material fields when quantity stays the same.
                $isUsed = (bool) $purchaseMovement->in_used;
                $existingQty = (float) ($purchaseMovement->quantity ?? 0);
                $incomingQty = (float) ($purchaseData['quantity'] ?? 0);
                $qtyChanged = abs($existingQty - $incomingQty) > 0.000000001;

                if ($isUsed && $qtyChanged) {
                    return ResponseHelper::error('Cannot update the purchased stock qty of used stock movement', 403,
                    [
                        'quantity' => ['The purchased item has already been used in production or scrap. Data cannot be updated to avoid data inconsistency']
                    ]);
                }

                $rawMaterial->update([
                    'material_name'            => $rawMaterialData['material_name'],
                    'barcode'                  => $rawMaterialData['barcode'] ?? null,
                    'minimum_stock_level'      => $rawMaterialData['minimum_stock_level'],
                    'description'              => $rawMaterialData['description'] ?? null,
                    'raw_material_category_id' => $rawMaterialData['raw_material_category_id'],
                    'base_uom_id'              => $rawMaterialData['base_uom_id'],
                    'supplier_id'              => $rawMaterialData['supplier_id'] ?? null,
                    'warehouse_id'             => $rawMaterialData['warehouse_id'],
                    'production_method'        => $rawMaterialData['production_method'] ?? null,
                ]);

                // 3) Append new images (keeps old, uploads new; max 4 total)
                if (!empty($incomingFiles)) {
                    $oldUrls = RMImage::query()
                        ->where('raw_material_id', $rawMaterial->id)
                        ->orderBy('id')
                        ->pluck('image')
                        ->all();

                    $availableSlots = max(0, 4 - count($oldUrls));
                    $filesToUpload = array_slice($incomingFiles, 0, $availableSlots);

                    if (!empty($filesToUpload)) {
                        // Append-mode helper returns merged URLs (old + new)
                        $mergedUrls = FileUploadHelper::uploadMultipleAppend($filesToUpload, 'raw-materials', $oldUrls);
                        $newUrls = array_slice($mergedUrls, count($oldUrls));

                        foreach ($newUrls as $url) {
                            RMImage::create([
                                'raw_material_id' => $rawMaterial->id,
                                'image' => $url,
                            ]);
                        }
                    }
                }

                $purchaseMovement->update([
                    'quantity' => $purchaseData['quantity'],
                    'direction' => StockDirectionEnum::IN->value,
                    'movement_type' => RawMaterialStockMovementTypeEnum::PURCHASE->value,
                    'movement_date' => $purchaseData['movement_date'],
                    'expiry_date' => $purchaseData['expiry_date'],
                    'unit_price_in_usd' => $purchaseData['unit_price_in_usd'],
                    'total_value_in_usd' => $purchaseData['total_value_in_usd'],
                    'exchange_rate_from_usd_to_riel' => $purchaseData['exchange_rate_from_usd_to_riel'],
                    'unit_price_in_riel' => $purchaseData['unit_price_in_riel'],
                    'total_value_in_riel' => $purchaseData['total_value_in_riel'],
                    'exchange_rate_from_riel_to_usd' => $purchaseData['exchange_rate_from_riel_to_usd'],
                    'last_updated_by' => $purchaseData['last_updated_by'],
                    'note' => $purchaseData['note'] ?? null,
                ]);

                $data = [
                    'raw_material' => $rawMaterial->fresh([
                        'rm_category',
                        'supplier' => fn ($q) => $q->withTrashed(),
                        'warehouse' => fn ($q) => $q->withTrashed(),
                        'uom' => fn ($q) => $q->withTrashed(),
                        'rm_stock_movements',
                        'rm_images',
                    ]),
                ];

                $newSnapshot = [
                    'raw_material' => $this->auditLoggerService->snapshotModel($data['raw_material']),
                    'purchase_movement' => $this->auditLoggerService->snapshotModel($purchaseMovement->fresh()),
                    'images' => $data['raw_material']->rm_images?->map(fn ($img) => [
                        'id' => (int) $img->id,
                        'image' => $img->image,
                    ])->values()->all() ?? [],
                ];

                $this->auditLoggerService->logDiff(
                    'raw_material.update',
                    RawMaterial::class,
                    (int) $rawMaterial->id,
                    $oldSnapshot,
                    $newSnapshot,
                    $this->getCurrentUserHelper->getUserId(),
                    ['context' => 'raw_material_service']
                );

                return ResponseHelper::success($data, 'Raw material updated successfully', 200);
            });
        }catch (ValidationException $e) {
            return ResponseHelper::validation($e -> errors(), 'Validation Error');
        }catch (Exception $e) {
            return ResponseHelper::error($e -> getMessage(), 500);
        }
    }

    // Delete Raw Material
    public function deleteRawMaterial ($rawMaterialId){
        try {
            $rawMaterial = RawMaterial::findOrFail($rawMaterialId);
            $oldSnapshot = $this->auditLoggerService->snapshotModel($rawMaterial);
            $rawMaterial->delete();

            $this->auditLoggerService->logChange(
                'raw_material.delete',
                RawMaterial::class,
                (int) $rawMaterial->id,
                $oldSnapshot,
                [],
                $this->getCurrentUserHelper->getUserId(),
                ['context' => 'raw_material_service']
            );

            return ResponseHelper::success(null , 'Raw material deleted successfully' , 200);
        }catch (Exception $e) {
            return ResponseHelper::error('Cannot delete this raw material' , 500 , $e -> getMessage());
        }
    }

    
    public function recoverRawMaterial($rawMaterialId){
        try {
            $rawMaterial = RawMaterial::withTrashed()->findOrFail($rawMaterialId);
            $oldSnapshot = $this->auditLoggerService->snapshotModel($rawMaterial);
            $rawMaterial->restore();

            $this->auditLoggerService->logChange(
                'raw_material.recover',
                RawMaterial::class,
                (int) $rawMaterial->id,
                $oldSnapshot,
                $this->auditLoggerService->snapshotModel($rawMaterial->fresh()),
                $this->getCurrentUserHelper->getUserId(),
                ['context' => 'raw_material_service']
            );

            return ResponseHelper::success(null , 'Raw material recovered successfully' , 200);
        }catch (Exception $e) {
            return ResponseHelper::error('Cannot recover this raw material' , 500 , $e -> getMessage());
        }
    }
    


    // Reorder Raw Material
    public function reorderRawMaterial(Request $request , $rawMaterialId)
    {
        try {
            $rawMaterial = RawMaterial::findOrFail($rawMaterialId);

            // Enforce reorder flow defaults
            $request->merge([
                'raw_material_id' => $rawMaterial->id,
                'direction' => StockDirectionEnum::IN->value,
                'movement_type' => RawMaterialStockMovementTypeEnum::RE_ORDER->value,
                'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
                'expiry_date' => $request->input('expiry_date', null),
            ]);

            // Only accept USD unit price + USD->Riel exchange rate as inputs;
            // compute everything else from quantity.
            $request->merge([
                'total_value_in_usd' => null,
                'unit_price_in_riel' => null,
                'total_value_in_riel' => null,
                'exchange_rate_from_riel_to_usd' => null,
            ]);

            // Always derive created_by / last_updated_by from the authenticated user
            $currentUserId = $this->getCurrentUserHelper->getUserId();
            $request->merge([
                'created_by'      => $currentUserId,
                'last_updated_by' => $currentUserId,
            ]);

            CurrencyPricingHelper::fillRMPurchasingCurrencyFields($request);

            $rules = $this->rmValidation->CreateRMStockMovementValidation($request);
            $validated = Validator::make($request->all(), $rules)->validate();

            $movement = DB::transaction(function () use ($validated, $rawMaterial) {
                return RMStockMovement::create([
                    'raw_material_id' => $rawMaterial->id,
                    'quantity' => $validated['quantity'],
                    'direction' => StockDirectionEnum::IN->value,
                    'movement_type' => RawMaterialStockMovementTypeEnum::RE_ORDER->value,
                    'movement_date' => $validated['movement_date'],
                    'expiry_date' => $validated['expiry_date'],
                    'unit_price_in_usd' => $validated['unit_price_in_usd'],
                    'total_value_in_usd' => $validated['total_value_in_usd'],
                    'exchange_rate_from_usd_to_riel' => $validated['exchange_rate_from_usd_to_riel'],
                    'unit_price_in_riel' => $validated['unit_price_in_riel'],
                    'total_value_in_riel' => $validated['total_value_in_riel'],
                    'exchange_rate_from_riel_to_usd' => $validated['exchange_rate_from_riel_to_usd'],
                    'created_by' => $validated['created_by'],
                    'last_updated_by' => $validated['last_updated_by'],
                    'note' => $validated['note'] ?? null,
                ]);
            });

            $this->auditLoggerService->logChange(
                'raw_material.reorder.create',
                RawMaterial::class,
                (int) $rawMaterial->id,
                [],
                ['movement' => $this->auditLoggerService->snapshotModel($movement)],
                (int) $currentUserId,
                ['context' => 'raw_material_service']
            );

            return ResponseHelper::success($movement, 'Raw material reordered successfully', 201);

        }catch (ValidationException $e){
            return ResponseHelper::validation($e -> errors() , 'Validation Error');
        }catch (Exception $e){
            return ResponseHelper::error($e -> getMessage() , 500);
        }
    }

    // Update Reorder Raw Material
    public function updateReorderRawMaterial(Request $request , $rawMaterialId , $movementId)
    {
        try {

            $rawMaterial = RawMaterial::findOrFail($rawMaterialId);
            $movement = RMStockMovement::findOrFail($movementId);


            // Enforce reorder flow defaults
            $request->merge([
                'raw_material_id' => $rawMaterial->id,
                'direction' => StockDirectionEnum::IN->value,
                'movement_type' => RawMaterialStockMovementTypeEnum::RE_ORDER->value,
                'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
                'expiry_date' => $request->input('expiry_date', null),
            ]);

            // Only accept USD unit price + USD->Riel exchange rate as inputs;
            // compute everything else from quantity.
            $request->merge([
                'total_value_in_usd' => null,
                'unit_price_in_riel' => null,
                'total_value_in_riel' => null,
                'exchange_rate_from_riel_to_usd' => null,
            ]);

            // Preserve original created_by; update last_updated_by to the acting user
            $request->merge([
                'created_by'      => $movement->created_by,
                'last_updated_by' => $this->getCurrentUserHelper->getUserId(),
            ]);

            // Check if the stock is already in used to avoid data incosistancy
            if ($movement -> in_used === true){
                return ResponseHelper::error('Cannot update used stock movement' , 401, 'The reordered material has been used. Data cannot be updated to avoid data inconsistency.');
            }


            CurrencyPricingHelper::fillRMPurchasingCurrencyFields($request);

            $rules = $this->rmValidation->CreateRMStockMovementValidation($request);
            $validated = Validator::make($request->all(), $rules)->validate();

            $oldSnapshot = $this->auditLoggerService->snapshotModel($movement);

            $movement = DB::transaction(function () use ($validated, $movement) {
                $movement->update([
                    'quantity' => $validated['quantity'],
                    'direction' => StockDirectionEnum::IN->value,
                    'movement_type' => RawMaterialStockMovementTypeEnum::RE_ORDER->value,
                    'movement_date' => $validated['movement_date'],
                    'expiry_date' => $validated['expiry_date'],
                    'unit_price_in_usd' => $validated['unit_price_in_usd'],
                    'total_value_in_usd' => $validated['total_value_in_usd'],
                    'exchange_rate_from_usd_to_riel' => $validated['exchange_rate_from_usd_to_riel'],
                    'unit_price_in_riel' => $validated['unit_price_in_riel'],
                    'total_value_in_riel' => $validated['total_value_in_riel'],
                    'exchange_rate_from_riel_to_usd' => $validated['exchange_rate_from_riel_to_usd'],
                    'last_updated_by' => $validated['last_updated_by'],
                    'note' => $validated['note'] ?? null,
                ]);

                return $movement->fresh();
            });

            $this->auditLoggerService->logDiff(
                'raw_material.reorder.update',
                RawMaterial::class,
                (int) $rawMaterial->id,
                $oldSnapshot,
                $this->auditLoggerService->snapshotModel($movement),
                $this->getCurrentUserHelper->getUserId(),
                ['context' => 'raw_material_service']
            );

            return ResponseHelper::success($movement, 'Raw material reordered updated successfully', 201);


        }catch (ValidationException $e){
            return ResponseHelper::validation($e -> errors() , 'Validation Error');
        }catch (Exception $e){
            return ResponseHelper::error($e -> getMessage() , 500);
        }
    }





    // Stock adjustment out service class
    public function adjustmentOut (Request $request , $rawMaterialId){
        try {
            
        $rawMaterial = RawMaterial::findOrFail($rawMaterialId);

            // Enforce stock adjustment out flow defaults
            $request->merge([
                'raw_material_id' => $rawMaterial->id,
                'direction' => StockDirectionEnum::OUT->value,
                'movement_type' => RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value,
                'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
            ]);

            // No pricing is calculated for adjustment out.
            // Force all pricing/rates to 0 to avoid requiring pricing inputs.
            $request->merge([
                'unit_price_in_usd' => 0,
                'total_value_in_usd' => 0,
                'exchange_rate_from_usd_to_riel' => 0,
                'unit_price_in_riel' => 0,
                'total_value_in_riel' => 0,
                'exchange_rate_from_riel_to_usd' => 0,
            ]);

            // Always derive created_by / last_updated_by from the authenticated user
            $currentUserId = $this->getCurrentUserHelper->getUserId();
            $request->merge([
                'created_by'      => $currentUserId,
                'last_updated_by' => $currentUserId,
            ]);

            $rules = $this->rmValidation->CreateRMStockMovementValidation($request);
            // Note must be required for adjustment out movements
            $rules['note'] = 'required|string';
            $validated = Validator::make($request->all(), $rules)->validate();

            // Check the qty in stock to avoid quantity is duduction over qty
            // Calculate current quantity in stock
            $currentQtyInStock = 0;
            foreach ($rawMaterial->rm_stock_movements as $movement) {
                $qty = (float) ($movement->quantity ?? 0);
                $currentQtyInStock += ($movement->direction === 'OUT') ? (-$qty) : $qty;
            }

            if ($currentQtyInStock < $validated['quantity']) {
                return ResponseHelper::error('Insuffiecient stock quantity', 401, [
                    'quantity' => ['Stock deduction qty must not be greater than current stock quantity.']
                ]);
            }

            $movement = DB::transaction(function () use ($validated, $rawMaterial) {
                return RMStockMovement::create([
                    'raw_material_id' => $rawMaterial->id,
                    'quantity' => $validated['quantity'],
                    'direction' => StockDirectionEnum::OUT->value,
                    'movement_type' => RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value,
                    'movement_date' => $validated['movement_date'],
                    'unit_price_in_usd' => 0,
                    'total_value_in_usd' => 0,
                    'exchange_rate_from_usd_to_riel' => 0,
                    'unit_price_in_riel' => 0,
                    'total_value_in_riel' => 0,
                    'exchange_rate_from_riel_to_usd' => 0,
                    'created_by' => $validated['created_by'],
                    'last_updated_by' => $validated['last_updated_by'],
                    'note' => $validated['note'],
                ]);
            });

            $this->auditLoggerService->logChange(
                'raw_material.adjustment_out.create',
                RawMaterial::class,
                (int) $rawMaterial->id,
                [],
                ['movement' => $this->auditLoggerService->snapshotModel($movement)],
                (int) $currentUserId,
                ['context' => 'raw_material_service']
            );

            return ResponseHelper::success($movement, 'Raw material adjustment out successfully', 201);


        }catch (ValidationException $e){
            return ResponseHelper::validation($e -> errors() , 'Validation Error');
        }catch (Exception $e){
            return ResponseHelper::error($e -> getMessage() , 500);
        }
    }




}




// Based on the raw material creation, Please implement raw material update feature.
// When update, The raw material stock movement which has the movement type of PURCHASE are not allow to update its type to ensure data consistancy and user can update field such as qty, unit price and exchange rate. For other raw material stock movement type beside PURCHASE can only update its type but has to be ensure that the current movement to update has to have limit of qty update since the qty of current one must not have bigger value than total sum of qty from previous one to ensure qty consistancy. 