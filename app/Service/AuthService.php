<?php 

namespace App\Service;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Helpers\ResponseHelper;

class AuthService
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $credentials = $request->only('email', 'password');

            $user = User::where('email', $credentials['email'])->first();
            if (!$user) {
                return ResponseHelper::error('The email is invalid.', 404, [
                    'email' => ['The email is invalid.']
                ]);
            }

            if (!Hash::check($credentials['password'], $user->password)) {
                return ResponseHelper::error('The password is incorrect.', 401, [
                    'password' => ['The password is incorrect.']
                ]);
            }

            $token = JWTAuth::claims(['role' => $user->role])->attempt($credentials);
            if (!$token) {
                return ResponseHelper::error('Invalid credentials', 401);
            }

            // $userDetails = $user->makeHidden(['password', 'otp_expires_at', 'otp', 'google_id']);

            return ResponseHelper::success([
                'user' => $user,
                'authorisation' => [
                    'token' => $token,
                    'type' => 'Bearer',
                ]
            ], 'Login successful', 200);

        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
