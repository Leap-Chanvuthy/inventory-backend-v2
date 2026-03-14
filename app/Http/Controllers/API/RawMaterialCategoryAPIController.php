<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\RawMaterialCategoryService;
use Illuminate\Http\Request;

class RawMaterialCategoryAPIController extends Controller
{

    protected $rawMaterialCategoryService;

    public function __construct(RawMaterialCategoryService $rawMaterialCategoryService)
    {
        $this->rawMaterialCategoryService = $rawMaterialCategoryService;
    }


    public function index(Request $request)
    {
        return $this->rawMaterialCategoryService->getAllRawMaterialCategories($request);
    }

    public function show($id)
    {
        return $this->rawMaterialCategoryService->getRawMaterialCategoryById($id);
    }

    public function store(Request $request)
    {
        return $this->rawMaterialCategoryService->createRawMaterialCategory($request);
    }

    public function update(Request $request, $id)
    {
        return $this->rawMaterialCategoryService->updateRawMaterialCategory($request, $id);
    }

    public function delete($id)
    {
        return $this->rawMaterialCategoryService->deleteRawMaterialCategory($id);
    }

    public function restore($id)
    {
        return $this->rawMaterialCategoryService->restoreRawMaterialCategory($id);
    }


    
}
