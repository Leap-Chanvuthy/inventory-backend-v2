<?php

namespace App\Service;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Helpers\ResponseHelper;
use App\Mail\PasswordResetMail;
use App\Mail\PaswordResetSuccessMail;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Service\TwoFactorService;

class AuthService
{
    public function __construct(private readonly TwoFactorService $twoFactorService)
    {
    }

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

            if ($user->email_verified_at === null) {
                return ResponseHelper::error('Email is not verified.', 403, 
                [
                    'email' => ['Email not verified. Please verify your email before logging in.']
                ]);
            }

            // If 2FA is enabled for this user, return a short-lived pending token.
            $pendingResponse = $this->twoFactorService->maybeCreatePendingLogin($user);
            if ($pendingResponse !== null) {
                return $pendingResponse;
            }

            $token = JWTAuth::claims(['role' => $user->role])->attempt($credentials);
            if (!$token) {
                return ResponseHelper::error('Invalid credentials', 401);
            }

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

    public function verifyTwoFactor(Request $request)
    {
        try {
            $request->validate([
                'two_factor_token' => 'required|string',
                'code' => 'nullable|string',
                'recovery_code' => 'nullable|string',
            ]);

            $pendingToken = $request->input('two_factor_token');
            $user = $this->twoFactorService->consumePendingLoginToken($pendingToken);

            if (!$user) {
                return ResponseHelper::error('Two-factor token is invalid or expired.', 401, [
                    'two_factor_token' => ['Two-factor token is invalid or expired.']
                ]);
            }

            $verified = $this->twoFactorService->verifyTotpOrRecoveryCode(
                user: $user,
                code: $request->input('code'),
                recoveryCode: $request->input('recovery_code'),
                consumeRecoveryCode: true
            );

            if ($verified !== true) {
                return $verified;
            }

            $this->twoFactorService->clearPendingLoginToken($pendingToken);

            // Issue normal JWT
            $token = JWTAuth::claims(['role' => $user->role])->fromUser($user);

            return ResponseHelper::success([
                'user' => $user,
                'authorisation' => [
                    'token' => $token,
                    'type' => 'Bearer',
                ]
            ], 'Login successful', 200);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation failed');
        } catch (Exception $e) {
            return ResponseHelper::error('Something went wrong', 500, ['error' => $e->getMessage()]);
        }
    }

    // public function sendResetLink(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email|exists:users,email',
    //     ]);

    //     $user = User::where('email', $request->email)->first();

    //     $token = Str::random(64);

    //     DB::table('password_reset_tokens')->updateOrInsert(
    //         ['email' => $user->email],
    //         [
    //             'token' => Hash::make($token),
    //             'created_at' => now(),
    //         ]
    //     );

    //     Mail::mailer('resend')->to($user->email)->send(new PasswordResetMail($token, $user->email));

    //     return ResponseHelper::success(null, 'Reset link sent successfully', 200);
    // }

    // public function reset(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'token' => 'required',
    //             'email' => 'required|email',
    //             'password' => 'required|min:8|confirmed',
    //         ]);

    //         $status = Password::reset(
    //             $request->only('email', 'password', 'password_confirmation', 'token'),
    //             function (User $user, string $password) {
    //                 $user->forceFill([
    //                     'password' => Hash::make($password),
    //                     'remember_token' => Str::random(60),
    //                 ])->save();
    //             }
    //         );

    //         if ($status === Password::PASSWORD_RESET) {
    //             Mail::to($request->email)->send(new PaswordResetSuccessMail(User::where('email', $request->email)->first()));
    //             return ResponseHelper::success(
    //                 ['message' => __($status)],
    //                 'Password reset successful',
    //                 200
    //             );
    //         }

    //         // Handle all failure cases
    //         return ResponseHelper::error(
    //             'Password reset failed',
    //             400,
    //             ['error' => __($status)]
    //         );
    //     } catch (Exception $e) {
    //         return ResponseHelper::error(
    //             'Something went wrong',
    //             500,
    //             ['error' => $e->getMessage()]
    //         );
    //     }
    // }

        public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Use the SAME broker repository that Password::reset() will use
        $token = Password::broker('users')->createToken($user);

        Mail::mailer('resend')->to($user->email)->send(
            new PasswordResetMail($token, $user->email)
        );

        return ResponseHelper::success(null, 'Reset link sent successfully', 200);
    }

    public function reset(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|min:8|confirmed',
            ]);

            $broker = Password::broker('users');

            $status = $broker->reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) use ($request) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    // Ensure single-use: remove any reset token rows for this email
                    DB::table('password_reset_tokens')
                        ->where('email', $request->email)
                        ->delete();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                Mail::to($request->email)->send(
                    new PaswordResetSuccessMail(User::where('email', $request->email)->first())
                );

                return ResponseHelper::success(
                    ['message' => __($status)],
                    'Password reset successful',
                    200
                );
            }

            return ResponseHelper::error(
                'Password reset failed',
                400,
                ['error' => __($status)]
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation failed');
        } catch (Exception $e) {
            return ResponseHelper::error(
                'Something went wrong',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

}
