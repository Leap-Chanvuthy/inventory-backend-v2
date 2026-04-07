<?php

namespace App\Http\Controllers;

use App\Service\TestService;
use Illuminate\Http\Request;

class TestAPIController extends Controller
{
    protected TestService $testService;

    public function __construct(TestService $testService)
    {
        $this->testService = $testService;
    }

    public function show($productId)
    {
        return $this->testService->getProductPnL((int)$productId);
    }
}
