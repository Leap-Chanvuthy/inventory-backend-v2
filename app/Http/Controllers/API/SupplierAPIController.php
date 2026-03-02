<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class SupplierAPIController extends Controller
{
    protected $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
    }


    public function index(Request $request)
    {
        return $this->supplierService->getAllSuppliers($request);
    }

    public function allDeleted(Request $request)
    {
        return $this->supplierService->allDeleted($request);
    }

    public function show($id)
    {
        return $this->supplierService->getSupplierById($id);
    }

    public function store(Request $request){
        return $this->supplierService->createSupplier($request);
    }

    public function update(Request $request, $id){
        return $this->supplierService->updateSupplier($request, $id);
    }

    public function delete ($id){
        return $this -> supplierService -> deleteSupplier($id);
    }

    public function recover($id)
    {
        return $this->supplierService->recoverSupplier($id);
    }

    public function import(Request $request)
    {
        return $this->supplierService->importSupplier($request);
    }

    public function getImportHistories(Request $request)
    {
        return $this->supplierService->getImportHistories($request);
    }

    public function getSupplierStatistics()
    {
        return $this->supplierService->getSupplierStatistics();
    }

    public function transactions(Request $request, $id)
    {
        return $this->supplierService->getSupplierTransactions($request, (int) $id);
    }

}
