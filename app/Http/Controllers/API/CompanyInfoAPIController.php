<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\CompanyInfoService;
use Illuminate\Http\Request;

class CompanyInfoAPIController extends Controller
{
    protected $companyService;

    public function __construct(CompanyInfoService $companyService)
    {
        $this->companyService = $companyService;
    }

    public function getCompanyInfo()
    {
        return $this->companyService->getCompanyInfo();
    }

    public function updateGeneral(Request $request)
    {
        return $this->companyService->updateGeneralInfo($request);
    }

    public function updateAddress(Request $request)
    {
        return $this->companyService->updateAddressInfo($request);
    }

    public function updateTelegram(Request $request)
    {
        return $this->companyService->updateTelegramInfo($request);
    }

    public function setupPayment(Request $request)
    {
        return $this->companyService->setupPayment($request);
    }

}
