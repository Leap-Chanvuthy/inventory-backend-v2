<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\WarehouseService;
use Illuminate\Http\Request;

class WarehouseAPIController extends Controller
{
    protected $warehouseService;
    public function __construct(WarehouseService $warehouseService)
    {
        $this->warehouseService = $warehouseService;
    }


    public function index(Request $request)
    {
        return $this->warehouseService->getAllWarehouses($request);
    }

    public function show($id)
    {
        return $this->warehouseService->getWarehouseById($id);
    }

    public function store(Request $request)
    {
        return $this->warehouseService->createWarehouse($request);
    }

    public function update(Request $request, $id)
    {
        return $this->warehouseService->updateWarehouse($request, $id);
    }

    public function delete($id)
    {
        return $this->warehouseService->deleteWarehouse($id);
    }

    public function deleteWarehouseImage($warehouseId, $imageId)
    {
        return $this->warehouseService->deleteWarehouseImage($warehouseId, $imageId);
    }

}
