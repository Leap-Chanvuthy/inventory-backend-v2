<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Interfaces\InvetoryDashboardAPIControllerInterface;
use App\Http\Requests\InventoryDashboardSummaryRequest;
use App\Service\InventoryDashboardService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class InventoryDashboardAPIController extends Controller implements InvetoryDashboardAPIControllerInterface
{
    public function __construct(protected InventoryDashboardService $inventoryDashboardService)
    {
    }

    public function summary(InventoryDashboardSummaryRequest $request): JsonResponse
    {
        try {
            $data = $this->inventoryDashboardService->summary($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Inventory dashboard summary retrieved successfully.',
                'data' => $data,
            ]);
        } catch (InvalidArgumentException $exception) {
            return ResponseHelper::error($exception->getMessage(), 422, ['date_range' => [$exception->getMessage()]]);  
        }
    }
}
