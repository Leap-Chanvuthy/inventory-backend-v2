<?php 

namespace App\Service;

use App\Helpers\QueryBuilderHelper;
use App\Helpers\ResponseHelper;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\RawMaterialCategory;
use App\Service\AuditLoggerService;
use Illuminate\Validation\ValidationException;

class RawMaterialCategoryService {

    protected AuditLoggerService $auditLoggerService;

    public function __construct(AuditLoggerService $auditLoggerService)
    {
        $this->auditLoggerService = $auditLoggerService;
    }

    public function rawMaterialBuilder(){
        return QueryBuilderHelper::build(
            model: RawMaterialCategory::class,
            joins: [],
            selects: [
                'raw_material_categories.id',
                'raw_material_categories.category_name',
                'raw_material_categories.label_color',
                'raw_material_categories.description',
                'raw_material_categories.deleted_at',
                'raw_material_categories.created_at',
                'raw_material_categories.updated_at',
            ],
            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('label_color'),
                AllowedFilter::callback('is_deleted', function (Builder $query, $value) {
                    $isDeleted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($isDeleted === true || (string) $value === '1') {
                        $query->onlyTrashed();
                        return;
                    }
                    $query->whereNull('raw_material_categories.deleted_at');
                }),

                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('raw_material_categories.category_name', 'LIKE', "%{$value}%");
                    });
                }),
            ],
            allowedSorts: [
                'created_at',
                'updated_at',
                'category_name',
            ],
            withCounts: [
                'raw_materials',
            ],
        );
    }


    public function getAllRawMaterialCategories(Request $request){
        try {
            $perPage = $request->input('per_page', 10);
            $categories = $this->rawMaterialBuilder()
                ->withTrashed()
                ->paginate($perPage);
            return ResponseHelper::success($categories, 'Raw material categories retrieved successfully', 200);
        }catch (Exception $e){
            return ResponseHelper::error('Failed to fetch raw material categories: ' . $e->getMessage(), 500);
        }
    }

    public function getRawMaterialCategoryById($id){
        try {

            $category = RawMaterialCategory::findOrFail($id);
            if (!$category) {
                return ResponseHelper::error('Raw material category not found', 404);
            }
            return ResponseHelper::success($category, 'Raw material category retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to fetch raw material category: ' . $e->getMessage(), 500);
        }
    }
    

    public function createRawMaterialCategory(Request $request){
        try {

            $validated = $request -> validate([
                'category_name' => 'required|string|max:255',
                'label_color' => 'nullable|string|max:50',
                'description' => 'nullable|string',
            ]);

            $category = RawMaterialCategory::create($validated);

            $this->auditLoggerService->logChange(
                'raw_material_category.create',
                RawMaterialCategory::class,
                (int) $category->id,
                [],
                $this->auditLoggerService->snapshotModel($category),
                null,
                ['context' => 'raw_material_category_service']
            );

            return ResponseHelper::success($category, 'Raw material category created successfully', 201);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to create raw material category: ' . $e->getMessage(), 500);
        }
    }


    public function updateRawMaterialCategory(Request $request, $id){
        try {

            $category = RawMaterialCategory::findOrFail($id);
            if (!$category) {
                return ResponseHelper::error('Raw material category not found', 404);
            }

            $validated = $request -> validate([
                'category_name' => 'sometimes|required|string|max:255',
                'label_color' => 'nullable|string|max:50',
                'description' => 'nullable|string',
            ]);

            $oldSnapshot = $this->auditLoggerService->snapshotModel($category);

            $category->update($validated);

            $newSnapshot = $this->auditLoggerService->snapshotModel($category->fresh());

            $this->auditLoggerService->logDiff(
                'raw_material_category.update',
                RawMaterialCategory::class,
                (int) $category->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['context' => 'raw_material_category_service']
            );

            return ResponseHelper::success($category, 'Raw material category updated successfully', 200);
        } catch (ValidationException $e){
            return ResponseHelper::validation($e->errors() , 'Validation Error', 422);
        } 
        catch (Exception $e) {
            return ResponseHelper::error('Failed to update raw material category: ' . $e->getMessage(), 500);
        }
    }


    public function deleteRawMaterialCategory ($id){
        try {

            $category = RawMaterialCategory::findOrFail($id);
            if (!$category) {
                return ResponseHelper::error('Raw material category not found', 404);
            }
            $oldSnapshot = $this->auditLoggerService->snapshotModel($category);

            $category->delete();

            $this->auditLoggerService->logChange(
                'raw_material_category.delete',
                RawMaterialCategory::class,
                (int) $category->id,
                $oldSnapshot,
                [],
                null,
                ['context' => 'raw_material_category_service']
            );

            return ResponseHelper::success(null, 'Raw material category deleted successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to delete raw material category: ' . $e->getMessage(), 500);
        }
    }

    public function restoreRawMaterialCategory($id){
        try {
            $category = RawMaterialCategory::withTrashed()->findOrFail($id);

            if (!$category->trashed()) {
                return ResponseHelper::error('Raw material category is already active', 400);
            }

            $category->restore();

            $newSnapshot = $this->auditLoggerService->snapshotModel($category->fresh());

            $this->auditLoggerService->logChange(
                'raw_material_category.restore',
                RawMaterialCategory::class,
                (int) $category->id,
                [],
                $newSnapshot,
                null,
                ['context' => 'raw_material_category_service']
            );

            return ResponseHelper::success($category, 'Raw material category restored successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to restore raw material category: ' . $e->getMessage(), 500);
        }
    }



}