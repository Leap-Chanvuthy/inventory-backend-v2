<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\ExternalProductReorder;
use App\Service\InternalProductReorder;
use App\Service\ProductScrap;
use App\Service\ProductService;
use Illuminate\Http\Request;

class ProductAPIController extends Controller
{
    protected ProductService $productService;
    protected ExternalProductReorder $externalProductReorder;
    protected InternalProductReorder $internalProductReorder;
    protected ProductScrap $productScrap;

    public function __construct(
        ProductService $productService,
        ExternalProductReorder $externalProductReorder,
        InternalProductReorder $internalProductReorder,
        ProductScrap $productScrap
    )
    {
        $this->productService = $productService;
        $this->externalProductReorder = $externalProductReorder;
        $this->internalProductReorder = $internalProductReorder;
        $this->productScrap = $productScrap;
    }

    public function index(Request $request)
    {
        return $this->productService->getAllProducts($request);
    }
    
    public function trashed(Request $request)
    {
        return $this->productService->getTrashedProducts($request);
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

    public function delete($id)
    {
        return $this->productService->deleteProduct($id);
    }
    

    public function restore($id)
    {
        return $this->productService->restoreProduct($id);
}


    // [REOREDER External] Reorder product (Create/Update) by external purchase (add stock)
    public function reorderExternalPurchase(Request $request, $id){
        return $this->externalProductReorder->reorderExternalPurchasedProduct($request, $id);
    }
    
    public function updateReorderExternalPurchase(Request $request, $productId, $movementId){
        return $this->externalProductReorder->updateReorderExternalPurchasedProduct($request, $productId, $movementId);
    }

    public function deleteReorderExternalPurchase($productId, $movementId)
    {
        return $this->externalProductReorder->deleteReorderExternalPurchasedProduct((int)$productId, (int)$movementId);
    }

    public function getReorderExternalPurchase($productId, $movementId)
    {
        return $this->externalProductReorder->getReorderExternalDetail((int)$productId, (int)$movementId);
    }
    // [REOREDER External] Reorder product (Create/Update) by external purchase (add stock)



    // [REOREDER INTERNAL] Reorder product (Create/Update) by internal manufacturing (add stock)
    public function reorderInternalManufacturing(Request $request, $id){
        return $this->internalProductReorder->reorderInternalManufacturedProduct($request, $id);
    }
    
    public function updateReorderInternalManufacturing(Request $request, $productId, $movementId){
        return $this->internalProductReorder->updateReorderInternalManufacturedProduct($request, $productId, $movementId);
    }

    public function deleteReorderInternalManufacturing($productId, $movementId)
    {
        return $this->internalProductReorder->deleteReorderInternalManufacturedProduct((int)$productId, (int)$movementId);
    }

    public function getReorderInternalManufacturing($productId, $movementId)
    {
        return $this->internalProductReorder->getReorderInternalDetail((int)$productId, (int)$movementId);
    }
    // [REOREDER INTERNAL] Reorder product (Create/Update) by internal manufacturing (add stock)

    public function createScrap(Request $request, $id)
    {
        return $this->productScrap->createScrapMovement($request, $id);
    }

    public function updateScrap(Request $request, $productId, $movementId)
    {
        return $this->productScrap->updateScrapMovement($request, $productId, $movementId);
    }

    public function getScrap($productId, $movementId)
    {
        return $this->productScrap->getScrapDetail((int)$productId, (int)$movementId);
    }


}
