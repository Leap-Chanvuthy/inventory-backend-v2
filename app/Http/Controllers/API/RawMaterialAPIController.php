<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\RMImageService;
use App\Service\RawMaterialService;
use Illuminate\Http\Request;

class RawMaterialAPIController extends Controller
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

    public function show($id)
    {
        return $this->rawMaterialService->getRawMaterialById($id);
    }

    public function store(Request $request)
    {
        return $this->rawMaterialService->createRawMaterial($request);
    }

    public function reorder(Request $request, int $rawMaterialId)
    {
        return $this->rawMaterialService->reorderRawMaterial($request, $rawMaterialId);
    }

    public function update(Request $request, int $id)
    {
        return $this->rawMaterialService->updateRawMaterial($id, $request);
    }

    public function deleteImages(Request $request, int $rawMaterialId)
    {
        return $this->rmImageService->deleteRawMaterialImages($rawMaterialId, $request);
    }

}
