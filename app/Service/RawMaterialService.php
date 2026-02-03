<?php

namespace App\Service;

use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Helpers\CurrencyPricingHelper;
use App\Helpers\FileUploadHelper;
use App\Helpers\ResponseHelper;
use App\Models\RMImage;
use App\Models\RMStockMovement;
use App\Models\RawMaterial;
use App\QueryBuilders\RawMaterialQueryBuilder;
use App\Validations\RMValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RawMaterialService
{
    protected RMValidation $rmValidation;
    protected RawMaterialQueryBuilder $rawMaterialQueryBuilder;

    public function __construct(
        RMValidation $rmValidation,
        RawMaterialQueryBuilder $rawMaterialQueryBuilder
        )
    {
        $this -> rmValidation = $rmValidation;
        $this -> rawMaterialQueryBuilder = $rawMaterialQueryBuilder;
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
                'rm_stock_movements',
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
            if ($rawMaterial->expiry_date) {
                // Mark as expired when expiry_date is today or earlier.
                $isExpired = $rawMaterial->expiry_date->startOfDay()->lte(now()->startOfDay());
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

            return DB::transaction(function () use ($request) {
                // 1) Create Raw Material
                $rawMaterialRules = $this->rmValidation->CreateRMValidation($request);
                $rawMaterialData = Validator::make($request->all(), $rawMaterialRules)->validate();

                $rawMaterial = RawMaterial::create([
                    'material_name' => $rawMaterialData['material_name'],
                    'material_sku_code' => $rawMaterialData['material_sku_code'],
                    'barcode' => $rawMaterialData['barcode'] ?? null,
                    'minimum_stock_level' => $rawMaterialData['minimum_stock_level'],
                    'expiry_date' => $rawMaterialData['expiry_date'],
                    'description' => $rawMaterialData['description'] ?? null,
                    'raw_material_category_id' => $rawMaterialData['raw_material_category_id'],
                    'uom_id' => $rawMaterialData['uom_id'],
                    'supplier_id' => $rawMaterialData['supplier_id'] ?? null,
                    'warehouse_id' => $rawMaterialData['warehouse_id'],
                ]);

                // 2) Create initial Stock Movement (PURCHASE / IN / now)
                $request->merge([
                    'raw_material_id' => $rawMaterial->id,
                    // keep supplier_id consistent with raw material selection
                    'supplier_id' => $request->input('supplier_id', $rawMaterial->supplier_id),
                    'direction' => 'IN',
                    'movement_type' => 'PURCHASE',
                    'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
                ]);

                CurrencyPricingHelper::fillRMPurchasingCurrencyFields($request);

                $stockMovementRules = $this->rmValidation->CreateRMStockMovementValidation($request);
                $stockMovementData = Validator::make($request->all(), $stockMovementRules)->validate();

                $movement = RMStockMovement::create([
                    'raw_material_id' => $rawMaterial->id,
                    'supplier_id' => $stockMovementData['supplier_id'],
                    'quantity' => $stockMovementData['quantity'],
                    'direction' => 'IN',
                    'movement_type' => 'PURCHASE',
                    'movement_date' => $stockMovementData['movement_date'],
                    'unit_price_in_usd' => $stockMovementData['unit_price_in_usd'],
                    'total_value_in_usd' => $stockMovementData['total_value_in_usd'],
                    'exchange_rate_from_usd_to_riel' => $stockMovementData['exchange_rate_from_usd_to_riel'],
                    'unit_price_in_riel' => $stockMovementData['unit_price_in_riel'],
                    'total_value_in_riel' => $stockMovementData['total_value_in_riel'],
                    'exchange_rate_from_riel_to_usd' => $stockMovementData['exchange_rate_from_riel_to_usd'],
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

                return ResponseHelper::success($data, 'Raw material created successfully', 201);
            });
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    
    


    // Reorder Raw Material
    public function reorderRawMaterial(Request $request , $rawMaterialId)
    {
        try {
            $rawMaterial = RawMaterial::findOrFail($rawMaterialId);

            // For data consistency: supplier_id must always come from the raw material.
            // Ignore/remove any supplier_id provided by clients.
            $request->request->remove('supplier_id');

            // Enforce reorder flow defaults
            $request->merge([
                'raw_material_id' => $rawMaterial->id,
                'supplier_id' => $rawMaterial->supplier_id,
                'direction' => StockDirectionEnum::IN->value,
                'movement_type' => RawMaterialStockMovementTypeEnum::RE_ORDER->value,
                'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
            ]);

            // Only accept USD unit price + USD->Riel exchange rate as inputs;
            // compute everything else from quantity.
            $request->merge([
                'total_value_in_usd' => null,
                'unit_price_in_riel' => null,
                'total_value_in_riel' => null,
                'exchange_rate_from_riel_to_usd' => null,
            ]);

            CurrencyPricingHelper::fillRMPurchasingCurrencyFields($request);

            $rules = $this->rmValidation->CreateRMStockMovementValidation($request);
            $validated = Validator::make($request->all(), $rules)->validate();

            $movement = DB::transaction(function () use ($validated, $rawMaterial) {
                return RMStockMovement::create([
                    'raw_material_id' => $rawMaterial->id,
                    'supplier_id' => $validated['supplier_id'],
                    'quantity' => $validated['quantity'],
                    'direction' => StockDirectionEnum::IN->value,
                    'movement_type' => RawMaterialStockMovementTypeEnum::RE_ORDER->value,
                    'movement_date' => $validated['movement_date'],
                    'unit_price_in_usd' => $validated['unit_price_in_usd'],
                    'total_value_in_usd' => $validated['total_value_in_usd'],
                    'exchange_rate_from_usd_to_riel' => $validated['exchange_rate_from_usd_to_riel'],
                    'unit_price_in_riel' => $validated['unit_price_in_riel'],
                    'total_value_in_riel' => $validated['total_value_in_riel'],
                    'exchange_rate_from_riel_to_usd' => $validated['exchange_rate_from_riel_to_usd'],
                    'note' => $validated['note'] ?? null,
                ]);
            });

            // $data = [
            //     'raw_material' => $rawMaterial->fresh([
            //         'rm_category' => fn($q) => $q -> withTrashed(),
            //         'supplier' => fn ($q) => $q->withTrashed(),
            //         'warehouse' => fn ($q) => $q->withTrashed(),
            //         'uom' => fn ($q) => $q->withTrashed(),
            //         'rm_stock_movements',
            //         'rm_images',
            //     ]),
            //     'stock_movement' => $movement,
            // ];

            return ResponseHelper::success($movement, 'Raw material reordered successfully', 201);

        }catch (ValidationException $e){
            return ResponseHelper::validation($e -> errors() , 'Validation Error');
        }catch (Exception $e){
            return ResponseHelper::error($e -> getMessage() , 500);
        }
    }




}






// I have updated some of my database fields as you can see in this validation fields:

// <?php 


// namespace App\Validations;


// use App\Helpers\GenerateUniqueSKU;
// use App\Helpers\CurrencyPricingHelper;
// use App\Models\RawMaterial;
// use App\Models\RawMaterialCategory;
// use Illuminate\Http\Request;

// class RMValidation {


//     // Handle the validation for creating a Raw Material
//     public function CreateRMValidation (Request $request): array
//     {
//         $rm = new RawMaterial();

//         $relations = [];
//         $format = '{prefix}-{random}';

//         // Use the category chosen by the user (raw_material_category_id)
//         if ($request->filled('raw_material_category_id')) {
//             $cat = RawMaterialCategory::find($request->input('raw_material_category_id'));

//             if ($cat) {
//                 $rm->rm_category()->associate($cat);
//                 $relations = ['cat' => 'rm_category.category_name'];
//                 $format = '{prefix}-{cat}-{random}';
//             }
//         }

//         if (!$request->filled('material_sku_code')) {
//             $request->merge([
//                 'material_sku_code' => GenerateUniqueSKU::generate(
//                     model: $rm,
//                     field: 'material_sku_code',
//                     randomLength: 6,
//                     prefix: 'RM',
//                     relations: $relations,
//                     format: $format
//                 )
//             ]);
//         }

//         return [
//             'material_name' => 'required|string|max:255',
//             'material_sku_code' => 'required|string|unique:raw_materials,material_sku_code|max:255',
//             'barcode' => 'nullable|string|max:255',
//             'minimum_quantity_stock_level' => 'required|numeric|min:0',
//             'expiry_date' => 'nullable|date',
//             'status' => 'required|in:IN_STOCK,OUT_OF_STOCK,LOW_STOCK,EXPIRED',
//             'description' => 'nullable|string',
//             'raw_material_category_id' => 'required|exists:raw_material_categories,id',
//             'uom_id' => 'required|exists:uoms,id',
//             'supplier_id' => 'nullable|exists:suppliers,id',
//             'warehouse_id' => 'required|exists:warehouses,id',
//         ];
//     }


//     public function CreateRMPurchasingTransactionValidation (Request $request): array
//     {
//         CurrencyPricingHelper::fillRMPurchasingCurrencyFields($request);

//         return [
//             'raw_material_id' => 'required|exists:raw_materials,id',
//             'supplier_id' => 'required|exists:suppliers,id',
//             'quantity' => 'required|numeric|min:0',
//             'transaction_date' => 'required|date',
//             'unit_price_in_usd' => 'required|numeric|min:0',
//             'total_value_in_usd' => 'required|numeric|min:0',
//             'exchange_rate_from_usd_to_riel' => 'required|numeric|min:0',
//             'unit_price_in_riel' => 'required|numeric|min:0',
//             'total_value_in_riel' => 'required|numeric|min:0',
//             'exchange_rate_from_riel_to_usd' => 'required|numeric|min:0',
//         ];
//     }


//     public function CreateRMStockMovementValidation (Request $request): array
//     {
//         return [
//             'raw_material_id' => 'required|exists:raw_materials,id',
//             'quantity' => 'required|numeric|min:0',
//             'direction' => 'required|in:IN,OUT',
//             'movement_type' => (function () use ($request) {
//                 if (!$request->filled('movement_type')) {
//                     $request->merge(['movement_type' => 'PURCHASE']);
//                 }
//                 return 'required|in:PURCHASE,RE_ORDER,SALE,PRODUCTION_SCRAP,PRODUCTION_RECEIPT,ADJUSTMENT_IN,ADJUSTMENT_OUT';
//             })(),
//             'movement_date' => 'required|date',
//         ];
//     }

//     public function CreateRMImageValidation (Request $request): array
//     {
//         return [
//             'raw_material_id' => 'required|exists:raw_materials,id',
//             'image' => 'required|image|max:2048',
//         ];
//     }
// }



// Please write me a raw material service to implement the create function of raw material. This is my flow and please implement as what I suggest:

// 1. Raw Material main data table:
//             'material_name' => 'required|string|max:255',
//             'material_sku_code' => 'required|string|unique:raw_materials,material_sku_code|max:255',
//             'barcode' => 'nullable|string|max:255',
//             'minimum_stock_level' => 'required|numeric|min:0',
//             'expiry_date' => 'nullable|date',
//             'description' => 'nullable|string',
//             'raw_material_category_id' => 'required|exists:raw_material_categories,id',
//             'uom_id' => 'required|exists:uoms,id',
//             'supplier_id' => 'nullable|exists:suppliers,id',
//             'warehouse_id' => 'required|exists:warehouses,id',

//             allow user to input the following information as needed. The default value fo the status is IN_STOCK since the very begining of the raw mateirial created is PURCHASE.


//            2. Raw Material Purchasing Transaction:
//            - Get the raw material id from the above feature
//            - Get supplier id from the above feature as well (This will useful for profit/loss that company spent with supplier).
//            - Use the currency helper to calculate pricing of this purchasing. Please allow user to input the usd currency with exchange from usd to khmer to calculate the khmer pricing. Please be noted that, the total value should be come from unit pricing multiple by quantity.


//            3. Stock Movement:
        //    - get the raw material from above feature
        //    - get quantity from purchasing transaction as an initail stock movement
        //    - the direction should be IN since it's is the stock increment
        //    - movement type should be PURCHASE in create function by default.
        //    - please added movement date as now.

//            4. Raw Material Image: Please use FileUploadHelper to implement the upload function. The image should be only 4 at max and below 2MB.