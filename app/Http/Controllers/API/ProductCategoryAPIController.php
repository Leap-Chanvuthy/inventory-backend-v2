<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\ProductCategoryService;
use Illuminate\Http\Request;

class ProductCategoryAPIController extends Controller
{
    protected $productCategoryService;

    public function __construct(ProductCategoryService $productCategoryService)
    {
        $this->productCategoryService = $productCategoryService;
    }


    public function index(Request $request)
    {
        return $this->productCategoryService->getAllProductCategories($request);
    }


    public function show($id)
    {
        return $this->productCategoryService->getProductCategoryById($id);
    }


    public function store(Request $request)
    {
        return $this->productCategoryService->createProductCategory($request);
    }


    public function update(Request $request, $id)
    {
        return $this->productCategoryService->updateProductCategory($request, $id);
    }

    public function delete($id)
    {
        return $this->productCategoryService->deleteProductCategory($id);
    }
    
}
