<?php 


namespace App\Service;

use App\Helpers\QueryBuilderHelper;
use App\Helpers\ResponseHelper;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\ProductCategory;
use App\Service\AuditLoggerService;

class ProductCategoryService {

    protected AuditLoggerService $auditLoggerService;

    public function __construct(AuditLoggerService $auditLoggerService)
    {
        $this->auditLoggerService = $auditLoggerService;
    }

    public function productCategoryBuilder(Request $request, bool $onlyTrashed = false)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $builder = QueryBuilderHelper::build(
            model: ProductCategory::class,

            joins: [
            ],

            selects: [
                'product_categories.*',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('label_color'),

                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                      $q->where('product_categories.category_name', 'LIKE', "%{$value}%");
                    });
                }),

                AllowedFilter::callback('category_name', function (Builder $query, $value) {
                    $query->where('product_categories.category_name', 'LIKE', "%{$value}%");
                }),
            ],

            allowedSorts: [
                'category_name',
                'created_at',
                'updated_at',
                'deleted_at',
            ],

            defaultSort: '-created_at',

            withRelations: [
            ],

            withCounts: [
                'products'
            ],
        );

        if ($onlyTrashed) {
            $builder = $builder->onlyTrashed();
        }

        return $builder
            ->paginate($perPage)
            ->appends($request->query());
    }


    public function getAllProductCategories (Request $request){
        try{
            $categories = $this -> productCategoryBuilder($request);

            return ResponseHelper::success($categories , 'Product categories retrieved successfully' , 200);
        }catch (Exception $e){
            return ResponseHelper::error('Failed to fetch product categories: ', 500 , $e->getMessage());
        }
    }


    public function getTrashedProductCategories(Request $request){
        try{
            $categories = $this -> productCategoryBuilder($request , true);
            return ResponseHelper::success($categories , 'Trashed product categories retrieved successfully' , 200);
        }catch (Exception $e){
            return ResponseHelper::error('Failed to fetch trashed product categories: ', 500 , $e->getMessage());
        }
    }


    public function getProductCategoryById ($id){
        try {
            $category = ProductCategory::findOrFail($id);
            if (!$category) {
                return ResponseHelper::error('Product category not found', 404);
            }
            return ResponseHelper::success($category, 'Product category retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to fetch product category: ' . $e->getMessage(), 500);
        }
    }


    public function createProductCategory (Request $request){
        try{
            $validated = $request -> validate([
                'category_name' => 'required|string|max:255',
                'label_color' => 'nullable|string|max:7',
                'description' => 'nullable|string',
            ]);

            $category = ProductCategory::create($validated);
            // Audit: record creation
            $this->auditLoggerService->logChange(
                'product_category.create',
                ProductCategory::class,
                (int) $category->id,
                [],
                $this->auditLoggerService->snapshotModel($category),
                null,
                ['context' => 'product_category_service']
            );

            return ResponseHelper::success($category , 'Product category created successfully' , 201);

        }catch (ValidationException $e){
            return ResponseHelper::validation($e->errors() , 'Validation Error' , 422);
        }catch (Exception $e){
            return ResponseHelper::error('Failed to create product category: ', 500, $e->getMessage());
        }
    }


    public function updateProductCategory (Request $request , $id){
        try{
            $category = ProductCategory::findOrFail($id);
            if (!$category) {
                return ResponseHelper::error('Product category not found', 404);
            }

            $validated = $request -> validate ([
                'category_name' => 'sometimes|required|string|max:255',
                'label_color' => 'nullable|string|max:7',
                'description' => 'nullable|string',
            ]);

            $oldSnapshot = $this->auditLoggerService->snapshotModel($category);

            $category -> update($validated);

            $newSnapshot = $this->auditLoggerService->snapshotModel($category->fresh());

            $this->auditLoggerService->logDiff(
                'product_category.update',
                ProductCategory::class,
                (int) $category->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['context' => 'product_category_service']
            );

            return ResponseHelper::success($category , 'Product category updated successfully' , 200);
        } catch (ValidationException $e){
            return ResponseHelper::validation($e->errors() , 'Validation Error' , 422);
        } catch (Exception $e){
            return ResponseHelper::error('Failed to update product category: ', 500, $e->getMessage());
        }
    }

    public function deleteProductCategory ($id){
        try{
            $category = ProductCategory::findOrFail($id);
            if (!$category) {
                return ResponseHelper::error('Product category not found', 404);
            }

            $oldSnapshot = $this->auditLoggerService->snapshotModel($category);

            $category -> delete();

            $this->auditLoggerService->logChange(
                'product_category.delete',
                ProductCategory::class,
                (int) $category->id,
                $oldSnapshot,
                [],
                null,
                ['context' => 'product_category_service']
            );

            return ResponseHelper::success(null , 'Product category deleted successfully' , 200);
        } catch (Exception $e){
            return ResponseHelper::error('Failed to delete product category: ', 500, $e->getMessage());
        }
    }


    public function restoreProductCategory ($id){
        try{
            $category = ProductCategory::onlyTrashed()->findOrFail($id);
            if (!$category) {
                return ResponseHelper::error('Product category not found', 404);
            }

            $oldSnapshot = $this->auditLoggerService->snapshotModel($category);

            $category -> restore();

            $newSnapshot = $this->auditLoggerService->snapshotModel($category->fresh());

            $this->auditLoggerService->logDiff(
                'product_category.restore',
                ProductCategory::class,
                (int) $category->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['context' => 'product_category_service']
            );

            return ResponseHelper::success($category , 'Product category restored successfully' , 200);
        } catch (Exception $e){
            return ResponseHelper::error('Failed to restore product category: ', 500, $e->getMessage());
        }
    }

    
}