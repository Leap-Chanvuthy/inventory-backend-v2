<?php

namespace App\Service;

use App\Helpers\QueryBuilderHelper;
use App\Helpers\ResponseHelper;
use App\Models\UomCategory;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;

class UomCategoryService
{
    protected function categoryBuilder()
    {
        return QueryBuilderHelper::build(
            model: UomCategory::class,
            joins: [],
            selects: [
                'uom_categories.id',
                'uom_categories.name',
                'uom_categories.description',
                'uom_categories.created_at',
                'uom_categories.updated_at',
            ],
            allowedFilters: [
                AllowedFilter::exact('id'),

                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('uom_categories.name', 'LIKE', "%{$value}%")
                          ->orWhere('uom_categories.description', 'LIKE', "%{$value}%");
                    });
                }),
            ],
            allowedSorts: [
                'name',
                'created_at',
                'updated_at',
            ],
            defaultSort: 'name',
        );
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * List active (non-deleted) categories with optional search + pagination.
     */
    public function getAllCategories(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 10);
            $perPage = max(1, min($perPage, 100));

            $categories = $this->categoryBuilder()
                ->withCount('unitOfMeasurements as units_count')
                ->with(['baseUnit'])
                ->paginate($perPage)
                ->appends($request->query());

            return ResponseHelper::success($categories, 'UOM categories retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to fetch UOM categories', 500, $e->getMessage());
        }
    }

    /**
     * List only soft-deleted categories (admin / restore workflow).
     */
    public function getTrashedCategories(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 10);
            $perPage = max(1, min($perPage, 100));

            $search = $request->input('filter.search')
                   ?? $request->input('filter[search]');

            $query = UomCategory::onlyTrashed()
                ->withCount('unitOfMeasurements as units_count')
                ->orderBy('name');

            if ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            return ResponseHelper::success(
                $query->paginate($perPage)->appends($request->query()),
                'Trashed UOM categories retrieved successfully',
                200
            );
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to fetch trashed UOM categories', 500, $e->getMessage());
        }
    }

    public function getCategoryById(int $id)
    {
        try {
            $category = UomCategory::withCount('unitOfMeasurements as units_count')
                ->with(['baseUnit'])
                ->findOrFail($id);

            return ResponseHelper::success($category, 'UOM category retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('UOM category not found', 404, $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    public function createCategory(Request $request)
    {
        try {
            $validated = $request->validate([
                // Only enforce uniqueness against non-deleted rows so that a
                // previously-deleted category name can be reused.
                'name' => [
                    'required', 'string', 'max:100',
                    Rule::unique('uom_categories', 'name')->whereNull('deleted_at'),
                ],
                'description' => 'nullable|string|max:500',
            ], [
                'name.required' => 'Please enter a category name.',
                'name.max'      => 'Category name must not exceed 100 characters.',
                'name.unique'   => 'A category with this name already exists. Please choose a different name.',
                'description.max' => 'Description must not exceed 500 characters.',
            ]);

            $category = UomCategory::create($validated);

            return ResponseHelper::success($category, 'UOM category created successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors());
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to create UOM category', 500, $e->getMessage());
        }
    }

    public function updateCategory(Request $request, int $id)
    {
        try {
            $category = UomCategory::findOrFail($id);

            $validated = $request->validate([
                'name' => [
                    'sometimes', 'required', 'string', 'max:100',
                    Rule::unique('uom_categories', 'name')
                        ->ignore($id)
                        ->whereNull('deleted_at'),
                ],
                'description' => 'nullable|string|max:500',
            ], [
                'name.required'   => 'Please enter a category name.',
                'name.max'        => 'Category name must not exceed 100 characters.',
                'name.unique'     => 'A category with this name already exists. Please choose a different name.',
                'description.max' => 'Description must not exceed 500 characters.',
            ]);

            $category->update($validated);

            return ResponseHelper::success($category->fresh(), 'UOM category updated successfully', 200);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors());
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to update UOM category', 500, $e->getMessage());
        }
    }

    /**
     * Soft-delete a category.
     * Child UOM records are NOT deleted — they become invisible via
     * UnitOfMeasurement::available() until the category is restored.
     */
    public function deleteCategory(int $id)
    {
        try {
            $category = UomCategory::findOrFail($id);
            $category->delete(); // SoftDeletes trait: sets deleted_at

            return ResponseHelper::success(null, 'UOM category archived successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to archive UOM category', 500, $e->getMessage());
        }
    }

    /**
     * Restore a previously soft-deleted category.
     * Once restored, all child UOM records become visible again automatically.
     */
    public function restoreCategory(int $id)
    {
        try {
            $category = UomCategory::onlyTrashed()->findOrFail($id);
            $category->restore();

            return ResponseHelper::success(
                $category->fresh(),
                'UOM category restored successfully',
                200
            );
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to restore UOM category', 500, $e->getMessage());
        }
    }
}
