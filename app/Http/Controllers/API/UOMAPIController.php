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

}
