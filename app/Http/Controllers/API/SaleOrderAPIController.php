<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Interfaces\SaleOrderAPIControllerInterface;
use Illuminate\Http\Request;
use App\Service\SaleOrderService;

class SaleOrderAPIController extends Controller implements SaleOrderAPIControllerInterface
{
    protected SaleOrderService $saleOrderService;

    public function __construct(SaleOrderService $saleOrderService)
    {
        $this->saleOrderService = $saleOrderService;
    }

    public function index(Request $request)
    {
        return $this->saleOrderService->index($request);
    }

    public function show(int $id)
    {
        return $this->saleOrderService->show($id);
    }

    public function store(Request $request)
    {
        return $this->saleOrderService->store($request);
    }

    public function update(Request $request, int $id)
    {
        return $this->saleOrderService->update($request, $id);
    }

    public function updateStatus(Request $request, int $id)
    {
        return $this->saleOrderService->updateStatus($request, $id);
    }

    public function delete(int $id)
    {
        return $this->saleOrderService->delete($id);
    }

    public function getStockAvailability(int $productId)
    {
        return $this->saleOrderService->getStockAvailability($productId);
    }

}
