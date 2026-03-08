<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UOMAPIController extends Controller
{
    protected $UOMService;

    public function __construct( 
        \App\Service\UOMService $UOMService
    )
    {
        $this->UOMService = $UOMService;
    }


    public function index(Request $request)
    {
        return $this->UOMService->getAllUOM($request);
    }

    public function show($id)
    {
        return $this->UOMService->getUOMById($id);
    }


    public function create(Request $request)
    {
        return $this->UOMService->createUOM($request);
    }

    public function update(Request $request, $id)
    {
        return $this->UOMService->updateUOM($request, $id);
    }

    public function delete ($id){
        return $this -> UOMService -> deleteUOM($id);
    }

    /**
     * Return paginated soft-deleted UOMs.
     * GET /uoms/trashed
     */
    public function trashed(Request $request)
    {
        return $this->UOMService->getTrashedUOMs($request);
    }

    /**
     * Restore a soft-deleted UOM.
     * PATCH /uoms/{id}/restore
     */
    public function restore($id)
    {
        return $this->UOMService->restoreUOM((int) $id);
    }

    /**
     * Convert a quantity from one UOM to another.
     * POST /uoms/convert
     */
    public function convert(Request $request)
    {
        return $this->UOMService->convertQuantity($request);
    }

}