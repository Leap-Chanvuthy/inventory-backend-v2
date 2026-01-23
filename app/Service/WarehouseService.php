<?php

namespace App\Service;

use App\Helpers\FileUploadHelper;
use App\Helpers\ImageDeleteHelper;
use App\Helpers\QueryBuilderHelper;
use App\Helpers\ResponseHelper;
use App\Models\Warehouse;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WarehouseService
{


    // public function WarehouseBuilder()
    // {

    //     return QueryBuilderHelper::build(
    //         model: Warehouse::class,
    //         joins: [],
    //         selects: [
    //             'warehouses.id',
    //             'warehouses.warehouse_name',
    //             'warehouses.warehouse_manager',
    //             'warehouses.warehouse_manager_contact',
    //             'warehouses.warehouse_manager_email',
    //             'warehouses.warehouse_address',
    //             'warehouses.latitude',
    //             'warehouses.longitude',
    //             'warehouses.warehouse_description',
    //             'warehouses.created_at',
    //             'warehouses.updated_at',
    //         ],

    //         allowedFilters: [
    //             AllowedFilter::exact('id'),
    //             AllowedFilter::exact('role'),

    //             AllowedFilter::callback('search', function (Builder $query, $value) {
    //                 $query->where(function ($q) use ($value) {
    //                     $q->where('warehouses.warehouse_name', 'LIKE', "%{$value}%")
    //                         ->orWhere('warehouses.warehouse_manager', 'LIKE', "%{$value}%");
    //                 });
    //             }),
    //         ],
    //         allowedSorts: [
    //             'id',
    //             'warehouse_manager',
    //             'created_at',
    //             'updated_at',
    //         ],
    //         withRelations: ['images'],
    //         withCounts: ['images']

    //     );
    // }


    // public function getAllWarehouses(Request $request)
    // {
    //     try {
    //         $request_per_page = $request->get('per_page', 10);
    //         $warehouse = $this->WarehouseBuilder()->paginate($request_per_page);
    //         return ResponseHelper::success($warehouse, 'Warehouses retrieved successfully', 200);
    //     } catch (Exception $e) {
    //         return ResponseHelper::error('Error querying warehoauses', 500, $e->getMessage());
    //     }
    // }

        public function WarehouseBuilder(Request $request)
    {
        // keep per_page logic here
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100)); // clamp 1..100

        return QueryBuilderHelper::build(
            model: Warehouse::class,
            joins: [],
            selects: [
                'warehouses.id',
                'warehouses.warehouse_name',
                'warehouses.warehouse_manager',
                'warehouses.warehouse_manager_contact',
                'warehouses.warehouse_manager_email',
                'warehouses.warehouse_address',
                'warehouses.latitude',
                'warehouses.longitude',
                'warehouses.warehouse_description',
                'warehouses.created_at',
                'warehouses.updated_at',
            ],
            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('role'),
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('warehouses.warehouse_name', 'LIKE', "%{$value}%")
                          ->orWhere('warehouses.warehouse_manager', 'LIKE', "%{$value}%");
                    });
                }),
            ],
            allowedSorts: [
                'id',
                'warehouse_manager',
                'created_at',
                'updated_at',
            ],
            withRelations: ['images'],
            withCounts: ['images']
        )
        ->paginate($perPage)
        ->appends($request->query());
    }

    public function getAllWarehouses(Request $request)
    {
        try {
            $warehouse = $this->WarehouseBuilder($request);
            return ResponseHelper::success($warehouse, 'Warehouses retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error querying warehoauses', 500, $e->getMessage());
        }
    }


    public function getWarehouseById($id)
    {
        try {
            $warehouse = Warehouse::with('images')->find($id);
            if (!$warehouse) {
                return ResponseHelper::error("Warehouse not found", 404, null);
            }
            return ResponseHelper::success($warehouse, "Warehouse retrieved successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error("Failed getting warehouse", 500, $e->getMessage());
        }
    }


    public function createWarehouse(Request $request)
    {
        try {
            $validated = $request->validate([
                'warehouse_name' => 'required|string|max:255',
                'warehouse_manager' => 'nullable|string|max:255',
                'warehouse_manager_contact' => 'nullable|string|max:255',
                'warehouse_manager_email' => 'nullable|email|max:255',
                'warehouse_address' => 'required|string',
                'latitude' => 'nullable|string|max:255',
                'longitude' => 'nullable|string|max:255',
                'warehouse_description' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            $images = $validated['images'] ?? null;
            unset($validated['images']);

            $warehouse = Warehouse::create($validated);

            if ($images) {
                $uploadedImages = FileUploadHelper::uploadMultipleAppend($images, 'warehouse_images');

                foreach ($uploadedImages as $imgUrl) {
                    $warehouse->images()->create([
                        'image' => $imgUrl
                    ]);
                }
            }

            $warehouse->load('images');

            return ResponseHelper::success($warehouse, "Warehouse created successfully", 201);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 'Validation failed while creating warehouse');
        } catch (Exception $e) {
            return ResponseHelper::error("Failed creating warehouse", 500, $e->getMessage());
        }
    }




    public function updateWarehouse(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'warehouse_name' => 'required|string|max:255',
                'warehouse_manager' => 'nullable|string|max:255',
                'warehouse_manager_contact' => 'nullable|string|max:255',
                'warehouse_manager_email' => 'nullable|email|max:255',
                'warehouse_address' => 'required|string',
                'latitude' => 'nullable|string|max:255',
                'longitude' => 'nullable|string|max:255',
                'warehouse_description' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            $warehouse = Warehouse::find($id);

            if (!$warehouse) {
                return ResponseHelper::error("Warehouse not found", 404);
            }

            // Extract images from validated data
            $images = $validated['images'] ?? null;
            unset($validated['images']);

            // Update fields
            $warehouse->update($validated);

            // Append images if provided
            if ($images) {
                $uploadedImages = FileUploadHelper::uploadMultipleAppend($images, 'warehouse_images');

                foreach ($uploadedImages as $imgUrl) {
                    $warehouse->images()->create([
                        'image' => $imgUrl
                    ]);
                }
            }

            // Load updated images
            $warehouse->load('images');

            return ResponseHelper::success($warehouse, "Warehouse updated successfully");
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 'Validation failed while updating warehouse');
        } catch (Exception $e) {
            return ResponseHelper::error("Failed updating warehouse", 500, $e->getMessage());
        }
    }


    public function deleteWarehouse($id)
    {
        try {
            $warehouse = Warehouse::find($id);
            if (!$warehouse) {
                return ResponseHelper::error("Warehouse not found", 404);
            }

            $warehouse->delete();
            return ResponseHelper::success(null, "Warehouse deleted successfully", 200);

        } catch (Exception $e) {
            return ResponseHelper::error("Failed deleting warehouse", 500, $e->getMessage());
        }
    }


    public function deleteWarehouseImage($warehouseId, $imageId)
    {
        try {
            $warehouse = Warehouse::find($warehouseId);
            if (!$warehouse) {
                return ResponseHelper::error("Warehouse not found", 404);
            }

            $image = $warehouse->images()->where('id', $imageId)->first();
            if (!$image) {
                return ResponseHelper::error("Image not found for this warehouse", 404);
            }

            ImageDeleteHelper::deleteSingle($image);

            return ResponseHelper::success(null, "Warehouse image deleted successfully" , 200);

        } catch (Exception $e) {
            return ResponseHelper::error("Failed deleting warehouse image", 500, $e->getMessage());
        }
    }




}
