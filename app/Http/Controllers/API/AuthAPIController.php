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

    public function verifyTwoFactor(Request $request)
    {
        return $this->authService->verifyTwoFactor($request);
    }

    public function sendResetLink(Request $request)
    {
        return $this->authService->sendResetLink($request);
    }

    public function resetPassword(Request $request){
        return $this -> authService -> reset($request);
    }

}
