<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for user authentication and password reset (Accessible for: All Users)"
 * )
 */
interface AuthAPIControllerInterface
{
    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Authentication"},
     *     summary="Login user and receive JWT token",
     *     description="User provides email and password. API returns user details and JWT Bearer token.",
     * 
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", example="admin@gmail.com"),
     *             @OA\Property(property="password", type="string", example="123456789")
     *         )
     *     ),
     *
    *     @OA\Response(
    *         response=200,
    *         description="Login successful OR two-factor required",
    *         @OA\JsonContent(
    *             oneOf={
    *                 @OA\Schema(
    *                     type="object",
    *                     @OA\Property(property="status", type="boolean", example=true),
    *                     @OA\Property(property="message", type="string", example="Login successful"),
    *                     @OA\Property(
    *                         property="data",
    *                         type="object",
    *                         @OA\Property(property="user", type="object"),
    *                         @OA\Property(
    *                             property="authorisation",
    *                             type="object",
    *                             @OA\Property(property="token", type="string", example="jwt-token-here"),
    *                             @OA\Property(property="type", type="string", example="Bearer")
    *                         )
    *                     )
    *                 ),
    *                 @OA\Schema(
    *                     type="object",
    *                     @OA\Property(property="status", type="boolean", example=true),
    *                     @OA\Property(property="message", type="string", example="Two-factor authentication required"),
    *                     @OA\Property(
    *                         property="data",
    *                         type="object",
    *                         @OA\Property(property="two_factor_required", type="boolean", example=true),
    *                         @OA\Property(property="two_factor_token", type="string", example="random-pending-token"),
    *                         @OA\Property(property="expires_in_seconds", type="integer", example=300)
    *                     )
    *                 )
    *             }
    *         )
    *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Invalid password",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The password is incorrect."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="password",
     *                     type="array",
     *                     @OA\Items(type="string", example="The password is incorrect.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Email not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The email is invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="The email is invalid.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function login();


    /**
     * @OA\Post(
     *     path="/api/login/2fa",
     *     tags={"Authentication"},
     *     summary="Verify two-factor code and receive JWT token",
     *     description="Second step for users with 2FA enabled. Provide the two_factor_token from /api/login plus either a TOTP code or a recovery_code.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"two_factor_token"},
     *             @OA\Property(property="two_factor_token", type="string", example="random-pending-token"),
     *             @OA\Property(property="code", type="string", nullable=true, example="123456"),
     *             @OA\Property(property="recovery_code", type="string", nullable=true, example="AB12CD34EF")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(
     *                     property="authorisation",
     *                     type="object",
     *                     @OA\Property(property="token", type="string", example="jwt-token-here"),
     *                     @OA\Property(property="type", type="string", example="Bearer")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Two-factor token invalid or expired",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Two-factor token is invalid or expired."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Invalid two-factor code",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid two-factor code"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function verifyTwoFactor();


    /**
     * @OA\Post(
     *     path="/api/send-reset-link",
     *     tags={"Authentication"},
     *     summary="Send reset password link",
     *     description="API generates a token and sends a password reset email with secure URL token.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", example="admin@gmail.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Reset link sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reset link sent successfully"),
     *             @OA\Property(property="data", type="string", nullable=true, example=null)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Email validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected email is invalid.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function sendResetLink();


    /**
     * @OA\Post(
     *     path="/api/reset-password",
     *     tags={"Authentication"},
     *     summary="Reset user password",
     *     description="Validate token + email + password and update the user password.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token","email","password","password_confirmation"},
     *             @OA\Property(property="token", type="string", example="generated-reset-token"),
     *             @OA\Property(property="email", type="string", example="admin@gmail.com"),
     *             @OA\Property(property="password", type="string", example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", example="newpassword123")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password reset successful"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="message", type="string", example="Your password has been reset!")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Password reset failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Password reset failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="This password reset token is invalid.")
     *             )
     *         )
     *     )
     * )
     */
    public function resetPassword();
}
