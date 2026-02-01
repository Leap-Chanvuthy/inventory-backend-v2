<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\RawMaterialService;
use Illuminate\Http\Request;

class RawMaterialAPIController extends Controller
{
    
    protected $rawMaterialService;

    public function __construct(
        RawMaterialService $rawMaterialService
    )
    {
        $this->rawMaterialService = $rawMaterialService;
    }


    public function index(Request $request)
    {
        //
    }

    public function store(Request $request)
    {
        return $this->rawMaterialService->createRawMaterial($request);
    }

}
