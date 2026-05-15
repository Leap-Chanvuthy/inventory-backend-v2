<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Interfaces\RawMaterialAPIControllerInterface;
use App\Service\RMImageService;
use App\Service\RawMaterialService;
use Illuminate\Http\Request;

class RawMaterialAPIController extends Controller implements RawMaterialAPIControllerInterface
{
    
    protected $rawMaterialService;
    protected $rmImageService;

    public function __construct(
        RawMaterialService $rawMaterialService,
        RMImageService $rmImageService
    )
    {
        $this->rawMaterialService = $rawMaterialService;
        $this->rmImageService = $rmImageService;
    }


    public function index(Request $request)
    {
        return $this ->  rawMaterialService -> getAllRawMaterials($request);
    }

    public function indexMovement(Request $request , int $rawMaterialId){
        return $this -> rawMaterialService -> getAllRawMaterialMovements($request , $rawMaterialId);
    }

    public function stockLots(Request $request, int $rawMaterialId)
    {
        return $this->rawMaterialService->getRawMaterialStockLots($request, $rawMaterialId);
    }

    public function scrapEligibleStockLots(Request $request, int $rawMaterialId)
    {
        return $this->rawMaterialService->getRawMaterialScrapEligibleStockLots($request, $rawMaterialId);
    }

    public function productionAllocationPreview(Request $request, int $rawMaterialId)
    {
        return $this->rawMaterialService->previewProductionAllocation($request, $rawMaterialId);
    }

    public function show($id)
    {
        return $this->rawMaterialService->getRawMaterialById($id);
    }

    public function allDeleted (Request $request){
        return $this->rawMaterialService->getAllDeletedRawMaterials($request);
    }

    public function store(Request $request)
    {
        return $this->rawMaterialService->createRawMaterial($request);
    }

    public function reorder(Request $request, int $rawMaterialId)
    {
        return $this->rawMaterialService->reorderRawMaterial($request, $rawMaterialId);
    }

    public function updateReorder (Request $request , int $rawMaterialId , int $movementId)
    {
        return $this->rawMaterialService->updateReorderRawMaterial($request, $rawMaterialId , $movementId);
    }

    public function delete($id)
    {
        return $this->rawMaterialService->deleteRawMaterial($id);
    }

    public function recover($id)
    {
        return $this->rawMaterialService->recoverRawMaterial($id);
    }


    public function adjustmentOut (Request $request , int $rawMaterialId)
    {
        return $this->rawMaterialService->adjustmentOut($request, $rawMaterialId);
    }

    public function createScrap(Request $request, int $rawMaterialId)
    {
        return $this->rawMaterialService->createScrapMovement($request, $rawMaterialId);
    }

    public function update(Request $request, int $id)
    {
        return $this->rawMaterialService->updateRawMaterial($id, $request);
    }

    public function deleteImages(Request $request, int $rawMaterialId)
    {
        return $this->rmImageService->deleteRawMaterialImages($rawMaterialId, $request);
    }

    public function uploadImages(Request $request, int $rawMaterialId)
    {
        return $this->rmImageService->uploadRawMaterialImages($rawMaterialId, $request);
    }

}
 
