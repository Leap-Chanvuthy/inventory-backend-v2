<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuditLogAPIController extends Controller
{
    //
    protected $auditLoggerService;

    public function __construct(
        \App\Service\AuditLoggerService $auditLoggerService
    )
    {
        $this->auditLoggerService = $auditLoggerService;
    }


    public function index(Request $request)
    {
        return $this->auditLoggerService->getAllAudits($request);
    }

    public function show($id)
    {
        return $this->auditLoggerService->getAuditById($id);
    }


}
