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

class CustomerService {
    
    protected $customerBuilder;
    protected $customerValidation;

    public function __construct(
        CustomerQueryBuilder $customerBuilder,
        CustomerValidation $customerValidation
        
    )
    {
        $this->customerBuilder = $customerBuilder;
        $this->customerValidation = $customerValidation;
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
            $customer = Customer::findOrFail($id);
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

            $customer->update($validated);
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
            $customer->delete();
            return ResponseHelper::success(null, "Customer deleted successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error deleting customer', 500, $e->getMessage());
        }
    }



}