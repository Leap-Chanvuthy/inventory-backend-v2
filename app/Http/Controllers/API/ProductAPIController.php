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

    public function updateExternalPurchase(Request $request, $id)
    {
        return $this->productService->updateExternalPurchasedProduct($request, $id);
    }

    public function storeInternalManufacturing(Request $request)
    {
        return $this->productService->createInternalManufacturedProduct($request);
    }

    public function updateInternalManufacturing(Request $request, $id)
    {
        return $this->productService->updateInternalManufacturedProduct($request, $id);
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

    // GET reorder details
    public function getReorderInternalManufacturing($productId, $movementId)
    {
        return $this->productService->getReorderInternalDetail((int)$productId, (int)$movementId);
    }

    public function getReorderExternalPurchase($productId, $movementId)
    {
        return $this->productService->getReorderExternalDetail((int)$productId, (int)$movementId);
    }

    public function createScrap(Request $request, $id)
    {
        return $this->productService->createScrapMovement($request, $id);
    }

    public function updateScrap(Request $request, $productId, $movementId)
    {
        return $this->productService->updateScrapMovement($request, $productId, $movementId);
    }

    public function getScrap($productId, $movementId)
    {
        return $this->productService->getScrapDetail((int)$productId, (int)$movementId);
    }


}
