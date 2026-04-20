<?php

namespace App\Service;

use App\Helpers\ResponseHelper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\CustomerCategory;
use App\QueryBuilders\CustomerCategoryQueryBuilder;
use App\Service\AuditLoggerService;


class CustomerCategoryService {

    protected AuditLoggerService $auditLoggerService;
    protected CustomerCategoryQueryBuilder $customerCategoryBuilder;

    public function __construct(AuditLoggerService $auditLoggerService , CustomerCategoryQueryBuilder $customerCategoryBuilder)
    {
        $this->auditLoggerService = $auditLoggerService;
        $this->customerCategoryBuilder = $customerCategoryBuilder;
    }


    public function getAllCustomerCategories (Request $request){
        try{
            
            $categories = $this->customerCategoryBuilder->customerCategoryBuilder($request);

            return ResponseHelper::success($categories , 'Customer categories retrieved successfully' , 200);
        }catch (Exception $e){
            return ResponseHelper::error('Failed to fetch customer categories: ', 500 , $e->getMessage());
        }
    }


    public function getTrashedCustomerCategories (Request $request){
        try{
            
            $categories = $this->customerCategoryBuilder->customerCategoryBuilder($request , true);

            return ResponseHelper::success($categories , 'Trashed customer categories retrieved successfully' , 200);
        }catch (Exception $e){
            return ResponseHelper::error('Failed to fetch trashed customer categories: ', 500 , $e->getMessage());
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
                'discount_percentage' => 'required|numeric|min:0|max:100',
            ]);

            // ensure numeric type for storage and response
            if (isset($validated['discount_percentage'])) {
                $validated['discount_percentage'] = (float) $validated['discount_percentage'];
            }

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
                'discount_percentage' => 'sometimes|required|numeric|min:0|max:100',
            ]);

            if (array_key_exists('discount_percentage', $validated)) {
                $validated['discount_percentage'] = (float) $validated['discount_percentage'];
            }

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


    public function restoreCustomerCategory($id){
        try {
            $category = CustomerCategory::onlyTrashed()->findOrFail($id);
            if (!$category) {
                return ResponseHelper::error('Customer category not found', 404);
            }

            $oldSnapshot = $this->auditLoggerService->snapshotModel($category);

            $category->restore();

            $newSnapshot = $this->auditLoggerService->snapshotModel($category->fresh());

            $this->auditLoggerService->logDiff(
                'customer_category.restore',
                CustomerCategory::class,
                (int) $category->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['context' => 'customer_category_service']
            );

            return ResponseHelper::success($category, 'Customer category restored successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to restore customer category: ' . $e->getMessage(), 500);
        }
    }
    
    
}