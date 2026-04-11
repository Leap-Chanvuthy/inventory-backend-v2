<?php

namespace App\Service;

use App\Helpers\FileUploadHelper;
use App\Helpers\ResponseHelper;
use App\Models\Customer;
use App\QueryBuilders\CustomerQueryBuilder;
use App\Validations\CustomerValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Service\AuditLoggerService;

class CustomerService {
    
    protected $customerBuilder;
    protected $customerValidation;
    protected AuditLoggerService $auditLoggerService;

    public function __construct(
        CustomerQueryBuilder $customerBuilder,
        CustomerValidation $customerValidation
        , AuditLoggerService $auditLoggerService
    )
    {
        $this->customerBuilder = $customerBuilder;
        $this->customerValidation = $customerValidation;
        $this->auditLoggerService = $auditLoggerService;
    }


    public function getCustomers(Request $request)
    {
        try {
            $customers = $this->customerBuilder->customerBuilder($request);
            return ResponseHelper::success($customers, "Customers retrieved successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error fetching customers', 500, $e->getMessage());
        }
    }


    public function getCustomerById($id){
        try {
            $customer = Customer::with(['customerCategory' => fn ($q) => $q->withTrashed()])-> findOrFail($id);
            if (!$customer) {
                return ResponseHelper::error('Customer not found', 404);
            }
            return ResponseHelper::success($customer, "Customer retrieved successfully", 200);
        }catch (Exception $e) {
            return ResponseHelper::error('Error fetching customer', 500, $e->getMessage());
        }
    }


    public function createCustomer(Request $request){
        try {
            $validated = $this -> customerValidation -> CreateValidation($request);
            if ($request->hasFile('image')) {
                $validated['image'] = FileUploadHelper::uploadSingle(
                    $request->file('image'),
                    'customers'
                );
            }

            $customer = Customer::create($validated);

            // Audit: record creation
            $this->auditLoggerService->logChange(
                'customer.create',
                Customer::class,
                (int) $customer->id,
                [],
                $this->auditLoggerService->snapshotModel($customer->load(['customerCategory'])),
                null,
                ['context' => 'customer_service']
            );
            return ResponseHelper::success($customer, "Customer created successfully", 201);
        }
        catch (ValidationException $ve){
            return ResponseHelper::validation($ve->errors() , 'Validation Errors', 422);
        } 
        catch (Exception $e) {
            return ResponseHelper::error('Error creating customer', 500, $e->getMessage());
        }
    }


    public function updateCustomer(Request $request, $id){
        try {
            $customer = Customer::findOrFail($id);
            if (!$customer) {
                return ResponseHelper::error('Customer not found', 404);
            }

            $validated = $this -> customerValidation -> UpdateValidation($request, $id);

            if ($request->hasFile('image')) {
                $validated['image'] = FileUploadHelper::uploadSingle(
                    $request->file('image'),
                    'customers'
                );
            }

            // Snapshot before update
            $oldSnapshot = $this->auditLoggerService->snapshotModel($customer->load(['customerCategory']));

            $customer->update($validated);
            $customer->refresh();

            // Snapshot after and log diff
            $newSnapshot = $this->auditLoggerService->snapshotModel($customer->fresh(['customerCategory']));
            $this->auditLoggerService->logDiff(
                'customer.update',
                Customer::class,
                (int) $customer->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['context' => 'customer_service']
            );

            return ResponseHelper::success($customer, "Customer updated successfully", 200);
        }
        catch (ValidationException $ve){
            return ResponseHelper::validation($ve->errors() , 'Validation Errors', 422);
        } 
        catch (Exception $e) {
            return ResponseHelper::error('Error updating customer', 500, $e->getMessage());
        }
    }


    public function deleteCustomer($id){
        try {
            $customer = Customer::findOrFail($id);
            if (!$customer) {
                return ResponseHelper::error('Customer not found', 404);
            }
            // Snapshot before delete
            $oldSnapshot = $this->auditLoggerService->snapshotModel($customer->load(['customerCategory']));

            $customer->delete();

            $this->auditLoggerService->logChange(
                'customer.delete',
                Customer::class,
                (int) $customer->id,
                $oldSnapshot,
                [],
                null,
                ['context' => 'customer_service']
            );

            return ResponseHelper::success(null, "Customer deleted successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error deleting customer', 500, $e->getMessage());
        }
    }



}