<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\AuthService;
use Illuminate\Http\Request;

class AuthAPIController extends Controller
{
    protected $authService;
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    public function login (Request $request)
    {
        return $this->authService->login($request);
    }
}
