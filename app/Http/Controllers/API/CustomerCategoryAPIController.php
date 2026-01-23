<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\CustomerCategoryService;
use Illuminate\Http\Request;

class CustomerCategoryAPIController extends Controller
{
    protected $customerCategoryService;

    public function __construct(CustomerCategoryService $customerCategoryService)
    {
        $this->customerCategoryService = $customerCategoryService;
    }


    public function index(Request $request)
    {
        return $this->customerCategoryService->getAllCustomerCategories($request);
    }


    public function show($id)
    {
        return $this->customerCategoryService->getCustomerCategoryById($id);
    }


    public function store(Request $request)
    {
        return $this->customerCategoryService->createCustomerCategory($request);
    }


    public function update(Request $request, $id)
    {
        return $this->customerCategoryService->updateCustomerCategory($request, $id);
    }

    public function delete($id)
    {
        return $this->customerCategoryService->deleteCustomerCategory($id);
    }

}
