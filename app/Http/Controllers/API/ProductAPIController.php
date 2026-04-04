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


    // Reorder product (Create/Update) by external purchase (add stock)
    public function reorderExternalPurchase(Request $request, $id){
        return $this->productService->reorderExternalPurchasedProduct($request, $id);
    }
    
    public function updateReorderExternalPurchase(Request $request, $productId, $movementId){
        return $this->productService->updateReorderExternalPurchasedProduct($request, $productId, $movementId);
    }


    public function reorderInternalManufacturing(Request $request, $id){
        return $this->productService->reorderInternalManufacturedProduct($request, $id);
    }
    
    public function updateReorderInternalManufacturing(Request $request, $productId, $movementId){
        return $this->productService->updateReorderInternalManufacturedProduct($request, $productId, $movementId);
    }

    public function createScrap(Request $request, $id)
    {
        return $this->productService->createScrapMovement($request, $id);
    }

    public function updateScrap(Request $request, $productId, $movementId)
    {
        return $this->productService->updateScrapMovement($request, $productId, $movementId);
    }


}
