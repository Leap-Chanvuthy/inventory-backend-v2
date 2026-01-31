<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(
 *   name="Two-Factor",
 *   description="Endpoints to setup, confirm, and disable two-factor authentication (TOTP)"
 * )
 */
interface TwoFactorAPIControllerInterface
{
    /**
     * @OA\Post(
     *   path="/api/two-factor/setup",
     *   tags={"Two-Factor"},
     *   security={{"Bearer":{}}},
     *   summary="Generate two-factor setup (secret + otpauth URL + recovery codes)",
     *   description="Creates a new TOTP secret for the current user and returns an otpauth URL (scan in Google Authenticator) plus one-time recovery codes. Call /api/two-factor/confirm to enable.",
     *
     *   @OA\Response(
     *     response=200,
     *     description="Two-factor setup generated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Two-factor setup generated successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
    *         @OA\Property(
    *           property="qr_code",
    *           type="string",
    *           description="PNG QR code as a base64 data URI (use it as an image source and scan with Google Authenticator).",
    *           example="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA"
    *         ),
     *         @OA\Property(property="secret", type="string", example="BASE32SECRET"),
     *         @OA\Property(
     *           property="recovery_codes",
     *           type="array",
     *           @OA\Items(type="string", example="AB12CD34EF")
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=500,
     *     description="Failed generating two-factor setup",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Failed generating two-factor setup"),
     *       @OA\Property(property="errors", type="string", nullable=true, example="Exception message here")
     *     )
     *   )
     * )
     */
    public function setup();

    /**
     * @OA\Post(
     *   path="/api/two-factor/confirm",
     *   tags={"Two-Factor"},
     *   security={{"Bearer":{}}},
     *   summary="Confirm and enable two-factor authentication",
     *   description="Verify a TOTP code from the authenticator app, then enables 2FA for the current user.",
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"code"},
     *       @OA\Property(property="code", type="string", example="123456")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Two-factor enabled successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Two-factor enabled successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="two_factor_enabled", type="boolean", example=true),
     *         @OA\Property(property="two_factor_confirmed_at", type="string", example="2026-01-30 12:00:00")
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=422,
     *     description="Invalid two-factor code",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Invalid two-factor code"),
     *       @OA\Property(property="errors", type="object")
     *     )
     *   )
     * )
     */
    public function confirm();

    /**
     * @OA\Post(
     *   path="/api/two-factor/disable",
     *   tags={"Two-Factor"},
     *   security={{"Bearer":{}}},
     *   summary="Disable two-factor authentication",
     *   description="Disables 2FA for the current user. Requires password and (if 2FA already enabled) a valid TOTP code or a recovery_code.",
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"password"},
     *       @OA\Property(property="password", type="string", example="123456789"),
     *       @OA\Property(property="code", type="string", nullable=true, example="123456"),
     *       @OA\Property(property="recovery_code", type="string", nullable=true, example="AB12CD34EF")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Two-factor disabled successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Two-factor disabled successfully"),
     *       @OA\Property(property="data", nullable=true, example=null)
     *     )
     *   )
     * )
     */
    public function disable();
}
