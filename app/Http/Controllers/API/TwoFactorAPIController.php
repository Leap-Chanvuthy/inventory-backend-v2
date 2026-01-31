<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\TwoFactorService;
use Illuminate\Http\Request;

class TwoFactorAPIController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactorService)
    {
    }

    public function setup(Request $request)
    {
        return $this->twoFactorService->setup($request->user());
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        return $this->twoFactorService->confirm($request->user(), $request->input('code'));
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        return $this->twoFactorService->disable(
            user: $request->user(),
            password: $request->input('password'),
            code: $request->input('code'),
            recoveryCode: $request->input('recovery_code')
        );
    }
}
