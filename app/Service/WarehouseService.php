<?php

namespace App\Service;

use App\Helpers\FileUploadHelper;
use App\Helpers\ImageDeleteHelper;
use App\Helpers\QueryBuilderHelper;
use App\Helpers\ResponseHelper;
use App\Models\SubWarehouse;
use App\Models\Warehouse;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WarehouseService
{

    protected $auditLoggerService;

    public function __construct(AuditLoggerService $auditLoggerService)
    {
        $this->auditLoggerService = $auditLoggerService;
    }

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
            withCounts: ['sub_warehouses' , 'images']
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
            $warehouse = Warehouse::with(['images', 'sub_warehouses'])->find($id);
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
                'sub_warehouses' => 'nullable|array',
                'sub_warehouses.*.warehouse_name' => 'required_with:sub_warehouses|string|max:255',
                'sub_warehouses.*.warehouse_manager' => 'nullable|string|max:255',
                'sub_warehouses.*.warehouse_manager_contact' => 'nullable|string|max:255',
                'sub_warehouses.*.warehouse_manager_email' => 'nullable|email|max:255',
                'sub_warehouses.*.warehouse_address' => 'required_with:sub_warehouses|string',
                'sub_warehouses.*.latitude' => 'nullable|string|max:255',
                'sub_warehouses.*.longitude' => 'nullable|string|max:255',
                'sub_warehouses.*.warehouse_description' => 'nullable|string',
            ]);

            $images = $validated['images'] ?? null;
            $subWarehouses = $validated['sub_warehouses'] ?? [];
            unset($validated['images']);
            unset($validated['sub_warehouses']);

            $warehouse = Warehouse::create($validated);

            if ($images) {
                $uploadedImages = FileUploadHelper::uploadMultipleAppend($images, 'warehouse_images');

                foreach ($uploadedImages as $imgUrl) {
                    $warehouse->images()->create([
                        'image' => $imgUrl
                    ]);
                }
            }

            if (!empty($subWarehouses)) {
                $warehouse->sub_warehouses()->createMany($this->normalizeSubWarehouses($subWarehouses));
            }

            $warehouse->load(['images', 'sub_warehouses']);

            // Audit: record warehouse creation
            $newSnapshot = $this->auditLoggerService->snapshotModel($warehouse);
            $this->auditLoggerService->logChange(
                'warehouse.create',
                Warehouse::class,
                $warehouse->id,
                [],
                $newSnapshot,
                null,
                ['description' => "Warehouse created with id: {$warehouse->id}"]
            );

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
                'sub_warehouses' => 'nullable|array',
                'sub_warehouses.*.warehouse_name' => 'required_with:sub_warehouses|string|max:255',
                'sub_warehouses.*.warehouse_manager' => 'nullable|string|max:255',
                'sub_warehouses.*.warehouse_manager_contact' => 'nullable|string|max:255',
                'sub_warehouses.*.warehouse_manager_email' => 'nullable|email|max:255',
                'sub_warehouses.*.warehouse_address' => 'required_with:sub_warehouses|string',
                'sub_warehouses.*.latitude' => 'nullable|string|max:255',
                'sub_warehouses.*.longitude' => 'nullable|string|max:255',
                'sub_warehouses.*.warehouse_description' => 'nullable|string',
            ]);

            $warehouse = Warehouse::find($id);

            if (!$warehouse) {
                return ResponseHelper::error("Warehouse not found", 404);
            }

            // Snapshot before changes for audit
            $oldSnapshot = $this->auditLoggerService->snapshotModel($warehouse->load(['images', 'sub_warehouses']));

            // Extract images from validated data
            $images = $validated['images'] ?? null;
            $subWarehouses = $validated['sub_warehouses'] ?? [];
            unset($validated['images']);
            unset($validated['sub_warehouses']);

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

            if (!empty($subWarehouses)) {
                $warehouse->sub_warehouses()->createMany($this->normalizeSubWarehouses($subWarehouses));
            }

            // Load updated images
            $warehouse->load(['images', 'sub_warehouses']);

            // Snapshot after update and log diff
            $warehouse->refresh();
            $newSnapshot = $this->auditLoggerService->snapshotModel($warehouse->load(['images', 'sub_warehouses']));
            $this->auditLoggerService->logDiff(
                'warehouse.update',
                Warehouse::class,
                $warehouse->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['description' => "Warehouse updated with id: {$warehouse->id}"]
            );

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

            if ($warehouse->sub_warehouses()->exists()) {
                return ResponseHelper::error(
                    "Cannot delete warehouse with existing sub warehouses. Please delete sub warehouses first.",
                    422
                );
            }

            $warehouse->delete();
            // Audit delete
            $oldSnapshot = $this->auditLoggerService->snapshotModel($warehouse);
            $this->auditLoggerService->logChange(
                'warehouse.delete',
                Warehouse::class,
                $warehouse->id,
                $oldSnapshot,
                [],
                null,
                ['description' => "Warehouse deleted with id: {$warehouse->id}"]
            );

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

            // Snapshot image before deletion for audit
            $oldImageSnapshot = $this->auditLoggerService->snapshotModel($image);
            $imageIdVal = $image->id;

            ImageDeleteHelper::deleteSingle($image);

            // Log image deletion (use the image model class dynamically)
            $this->auditLoggerService->logChange(
                'warehouse.delete_image',
                get_class($image),
                $imageIdVal,
                $oldImageSnapshot,
                [],
                null,
                ['description' => "Warehouse image deleted with id: {$imageIdVal} from warehouse: {$warehouse->id}"]
            );

            return ResponseHelper::success(null, "Warehouse image deleted successfully" , 200);

        } catch (Exception $e) {
            return ResponseHelper::error("Failed deleting warehouse image", 500, $e->getMessage());
        }
    }

    public function updateSubWarehouse(Request $request, $warehouseId, $subWarehouseId)
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
            ]);

            $warehouse = Warehouse::find($warehouseId);
            if (!$warehouse) {
                return ResponseHelper::error("Warehouse not found", 404);
            }

            $subWarehouse = $warehouse->sub_warehouses()->find($subWarehouseId);
            if (!$subWarehouse) {
                return ResponseHelper::error("Sub warehouse not found", 404);
            }

            $oldSnapshot = $this->auditLoggerService->snapshotModel($subWarehouse);
            $subWarehouse->update($validated);
            $subWarehouse->refresh();

            $this->auditLoggerService->logDiff(
                'warehouse.sub_warehouse.update',
                SubWarehouse::class,
                $subWarehouse->id,
                $oldSnapshot,
                $this->auditLoggerService->snapshotModel($subWarehouse),
                null,
                ['description' => "Sub warehouse updated with id: {$subWarehouse->id} for warehouse: {$warehouse->id}"]
            );

            return ResponseHelper::success($subWarehouse, "Sub warehouse updated successfully", 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 'Validation failed while updating sub warehouse');
        } catch (Exception $e) {
            return ResponseHelper::error("Failed updating sub warehouse", 500, $e->getMessage());
        }
    }

    public function deleteSubWarehouse($warehouseId, $subWarehouseId)
    {
        try {
            $warehouse = Warehouse::find($warehouseId);
            if (!$warehouse) {
                return ResponseHelper::error("Warehouse not found", 404);
            }

            $subWarehouse = $warehouse->sub_warehouses()->find($subWarehouseId);
            if (!$subWarehouse) {
                return ResponseHelper::error("Sub warehouse not found", 404);
            }

            $oldSnapshot = $this->auditLoggerService->snapshotModel($subWarehouse);
            $deletedId = $subWarehouse->id;
            $subWarehouse->delete();

            $this->auditLoggerService->logChange(
                'warehouse.sub_warehouse.delete',
                SubWarehouse::class,
                $deletedId,
                $oldSnapshot,
                [],
                null,
                ['description' => "Sub warehouse deleted with id: {$deletedId} from warehouse: {$warehouse->id}"]
            );

            return ResponseHelper::success(null, "Sub warehouse deleted successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error("Failed deleting sub warehouse", 500, $e->getMessage());
        }
    }

    private function normalizeSubWarehouses(array $subWarehouses): array
    {
        return collect($subWarehouses)->map(function ($item) {
            return [
                'warehouse_name' => $item['warehouse_name'],
                'warehouse_manager' => $item['warehouse_manager'] ?? null,
                'warehouse_manager_contact' => $item['warehouse_manager_contact'] ?? null,
                'warehouse_manager_email' => $item['warehouse_manager_email'] ?? null,
                'warehouse_address' => $item['warehouse_address'],
                'latitude' => $item['latitude'] ?? null,
                'longitude' => $item['longitude'] ?? null,
                'warehouse_description' => $item['warehouse_description'] ?? null,
            ];
        })->values()->all();
    }


}
