<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\RMStockMovementService;
use Illuminate\Http\Request;

class RMStockMovementAPIController extends Controller
{
    public function __construct(
        protected RMStockMovementService $rmStockMovementService
    ) {
    }

    public function store(Request $request, int $rawMaterialId)
    {
        return $this->rmStockMovementService->createForRawMaterial($request, $rawMaterialId);
    }
}
