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

    public function statistics(Request $request)
    {
        return $this->saleOrderService->statistics($request);
    }

    public function statisticsReport(Request $request)
    {
        return $this->saleOrderService->exportStatisticsReport($request);
    }

    public function saleOrderReport(int $id)
    {
        return $this->saleOrderService->exportSaleOrderReport($id);
    }

    public function show(int $id)
    {
        return $this->saleOrderService->show($id);
    }

    public function refundRecords(Request $request)
    {
        return $this->saleOrderService->listRefundRecords($request);
    }

    public function refunds(int $id)
    {
        return $this->saleOrderService->getRefunds($id);
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

    public function addInstallment(Request $request, int $id)
    {
        return $this->saleOrderService->addInstallment($request, $id);
    }

    public function addPayment(Request $request, int $id)
    {
        return $this->saleOrderService->addPayment($request, $id);
    }

    public function updateLatestInstallment(Request $request, int $id)
    {
        return $this->saleOrderService->updateLatestInstallment($request, $id);
    }

    public function refund(Request $request, int $id)
    {
        return $this->saleOrderService->refund($request, $id);
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
