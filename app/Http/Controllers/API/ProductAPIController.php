<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\ProductService;
use Illuminate\Http\Request;

class ProductAPIController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index(Request $request)
    {
        return $this->productService->getAllProducts($request);
    }
    
    public function show($id){
        return $this->productService->getProductDetail($id);
    }

    public function storeExternalPurchase(Request $request)
    {
        return $this->productService->createExternalPurchasedProduct($request);
    }

    public function storeInternalManufacturing(Request $request)
    {
        return $this->productService->createInternalManufacturedProduct($request);
    }
}
