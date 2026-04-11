<?php

namespace App\Service;

use App\Helpers\QueryBuilderHelper;
use App\Helpers\ResponseHelper;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\CustomerCategory;
use App\Service\AuditLoggerService;


class CustomerCategoryService {

    protected AuditLoggerService $auditLoggerService;

    public function __construct(AuditLoggerService $auditLoggerService)
    {
        $this->auditLoggerService = $auditLoggerService;
    }

    private function customerCategoryBuilder(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return QueryBuilderHelper::build(
            model: CustomerCategory::class,

            joins: [],
            selects: [
                'customer_categories.id',
                'customer_categories.category_name',
                'customer_categories.label_color',
                'customer_categories.description',
                'customer_categories.created_at',
                'customer_categories.updated_at',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('role'),

                // Search by name / email / phone_number
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('customer_categories.category_name', 'LIKE', "%{$value}%")
                            ->orWhere('customer_categories.description', 'LIKE', "%{$value}%");
                    });
                }),
            ],

            allowedSorts: [
                'id',
                'category_name',
                'created_at',
                'updated_at',
            ],

            defaultSort: '-created_at'
        )
        ->paginate($perPage)
        ->appends($request->query());
        ;
    }


    public function getAllCustomerCategories (Request $request){
        try{
            
            $categories = $this->customerCategoryBuilder($request);

            return ResponseHelper::success($categories , 'Customer categories retrieved successfully' , 200);
        }catch (Exception $e){
            return ResponseHelper::error('Failed to fetch customer categories: ', 500 , $e->getMessage());
        }
    }


    public function getCustomerCategoryById ($id){
        try {
            $category = CustomerCategory::findOrFail($id);
            if (!$category) {
                return ResponseHelper::error('Customer category not found', 404);
            }
            return ResponseHelper::success($category, 'Customer category retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to fetch customer category: ' . $e->getMessage(), 500);
        }
    }


    public function createCustomerCategory (Request $request){
        try{
            $validated = $request -> validate([
                'category_name' => 'required|string|max:255',
                'label_color' => 'nullable|string|max:7',
                'description' => 'nullable|string',
            ]);

            $category = CustomerCategory::create($validated);

            // Audit: record creation
            $this->auditLoggerService->logChange(
                'customer_category.create',
                CustomerCategory::class,
                (int) $category->id,
                [],
                $this->auditLoggerService->snapshotModel($category),
                null,
                ['context' => 'customer_category_service']
            );

            return ResponseHelper::success($category , 'Customer category created successfully' , 201);

        }catch (ValidationException $e){
            return ResponseHelper::validation($e->errors() , 'Validation Error' , 422);
        }catch (Exception $e){
            return ResponseHelper::error('Failed to create customer category: ', 500, $e->getMessage());
        }
    }


    public function updateCustomerCategory (Request $request , $id){
        try{
            $category = CustomerCategory::findOrFail($id);
            if (!$category) {
                return ResponseHelper::error('Customer category not found', 404);
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
                'customer_category.update',
                CustomerCategory::class,
                (int) $category->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['context' => 'customer_category_service']
            );

            return ResponseHelper::success($category , 'Product category updated successfully' , 200);
        } catch (ValidationException $e){
            return ResponseHelper::validation($e->errors() , 'Validation Error' , 422);
        } catch (Exception $e){
            return ResponseHelper::error('Failed to update product category: ', 500, $e->getMessage());
        }
    }

    public function deleteCustomerCategory($id){
        try {
            $category = CustomerCategory::findOrFail($id);
            if (!$category) {
                return ResponseHelper::error('Customer category not found', 404);
            }

            $oldSnapshot = $this->auditLoggerService->snapshotModel($category);

            $category->delete();

            $this->auditLoggerService->logChange(
                'customer_category.delete',
                CustomerCategory::class,
                (int) $category->id,
                $oldSnapshot,
                [],
                null,
                ['context' => 'customer_category_service']
            );

            return ResponseHelper::success(null, 'Customer category deleted successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to delete customer category: ' . $e->getMessage(), 500);
        }
    }
    
    
}