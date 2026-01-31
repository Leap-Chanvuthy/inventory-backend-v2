<?php

namespace App\Service;

use App\Helpers\ResponseHelper;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Str;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Exception;

class TwoFactorService
{
    private const PENDING_CACHE_PREFIX = 'two_factor_login:';
    private const PENDING_TTL_SECONDS = 300; // 5 minutes

    public function setup(User $user)
    {
        try {
            $google2fa = new Google2FA();

            $secret = $google2fa->generateSecretKey();

            $user->two_factor_enabled = false;
            $user->two_factor_confirmed_at = null;
            $user->two_factor_secret = Crypt::encryptString($secret);

            $recoveryCodesPlain = $this->generateRecoveryCodes();
            $user->two_factor_recovery_codes = json_encode($this->hashRecoveryCodes($recoveryCodesPlain));

            $user->save();

            $issuer = config('app.name', 'IMS');
            $label = $user->email;

            $otpauthUrl = $google2fa->getQRCodeUrl($issuer, $label, $secret);

            // Return a scannable QR code image (PNG) as a data URI string.
            // Frontend can render it directly in <img src="..." />.
            $qrCode = new QrCode(
                data: $otpauthUrl,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 280,
                margin: 10
            );

            $writer = new PngWriter();
            $png = $writer->write($qrCode)->getString();
            $qrCodeDataUri = 'data:image/png;base64,' . base64_encode($png);

            return ResponseHelper::success([
                'qr_code' => $qrCodeDataUri,
                'secret' => $secret,
                'recovery_codes' => $recoveryCodesPlain,
            ], 'Two-factor setup generated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed generating two-factor setup', 500, $e->getMessage());
        }
    }

    public function confirm(User $user, string $code)
    {
        try {
            if (!$user->two_factor_secret) {
                return ResponseHelper::error('Two-factor is not initialized. Please setup first.', 400);
            }

            $secret = Crypt::decryptString($user->two_factor_secret);
            $google2fa = new Google2FA();

            if (!$google2fa->verifyKey($secret, $code)) {
                return ResponseHelper::error('Invalid two-factor code', 422, ['code' => ['Invalid code.']]);
            }

            $user->two_factor_enabled = true;
            $user->two_factor_confirmed_at = now();
            $user->save();

            return ResponseHelper::success([
                'two_factor_enabled' => true,
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
            ], 'Two-factor enabled successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed confirming two-factor', 500, $e->getMessage());
        }
    }

    public function disable(User $user, string $password, ?string $code = null, ?string $recoveryCode = null)
    {
        try {
            if (!Hash::check($password, $user->password)) {
                return ResponseHelper::error('The password is incorrect.', 401, [
                    'password' => ['The password is incorrect.']
                ]);
            }

            if ($user->two_factor_enabled) {
                $verified = $this->verifyTotpOrRecoveryCode($user, $code, $recoveryCode, consumeRecoveryCode: false);
                if ($verified !== true) {
                    return $verified; // ResponseHelper error
                }
            }

            $user->two_factor_enabled = false;
            $user->two_factor_secret = null;
            $user->two_factor_confirmed_at = null;
            $user->two_factor_recovery_codes = null;
            $user->save();

            return ResponseHelper::success(null, 'Two-factor disabled successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed disabling two-factor', 500, $e->getMessage());
        }
    }

    /**
     * Called by login when password is correct.
     * Returns either a pending token response, or null when 2FA not required.
     */
    public function maybeCreatePendingLogin(User $user)
    {
        if (!$user->two_factor_enabled || !$user->two_factor_confirmed_at || !$user->two_factor_secret) {
            return null;
        }

        $pendingToken = Str::random(64);

        Cache::put(
            self::PENDING_CACHE_PREFIX . $pendingToken,
            ['user_id' => $user->id],
            self::PENDING_TTL_SECONDS
        );

        return ResponseHelper::success([
            'two_factor_required' => true,
            'two_factor_token' => $pendingToken,
            'expires_in_seconds' => self::PENDING_TTL_SECONDS,
        ], 'Two-factor authentication required', 200);
    }

    public function consumePendingLoginToken(string $pendingToken): ?User
    {
        $payload = Cache::get(self::PENDING_CACHE_PREFIX . $pendingToken);
        if (!$payload || !isset($payload['user_id'])) {
            return null;
        }

        return User::find($payload['user_id']);
    }

    public function clearPendingLoginToken(string $pendingToken): void
    {
        Cache::forget(self::PENDING_CACHE_PREFIX . $pendingToken);
    }

    public function verifyTotpOrRecoveryCode(User $user, ?string $code, ?string $recoveryCode, bool $consumeRecoveryCode = true)
    {
        if (!$user->two_factor_secret) {
            return ResponseHelper::error('Two-factor is not configured for this user.', 400);
        }

        $code = $code !== null ? trim($code) : null;
        $recoveryCode = $recoveryCode !== null ? trim($recoveryCode) : null;

        if ($code === null && $recoveryCode === null) {
            return ResponseHelper::error('Two-factor code is required.', 422, [
                'code' => ['Two-factor code is required.']
            ]);
        }

        if ($code !== null && $code !== '') {
            $secret = Crypt::decryptString($user->two_factor_secret);
            $google2fa = new Google2FA();

            if ($google2fa->verifyKey($secret, $code)) {
                return true;
            }
        }

        if ($recoveryCode !== null && $recoveryCode !== '') {
            $stored = $this->getRecoveryCodesHashed($user);
            $hash = hash('sha256', $recoveryCode);

            $index = array_search($hash, $stored, true);
            if ($index !== false) {
                if ($consumeRecoveryCode) {
                    unset($stored[$index]);
                    $user->two_factor_recovery_codes = json_encode(array_values($stored));
                    $user->save();
                }
                return true;
            }
        }

        return ResponseHelper::error('Invalid two-factor code', 422, [
            'code' => ['Invalid code.']
        ]);
    }

    private function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(10));
        }
        return $codes;
    }

    private function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn ($c) => hash('sha256', $c), $codes);
    }

    private function getRecoveryCodesHashed(User $user): array
    {
        if (!$user->two_factor_recovery_codes) {
            return [];
        }

        $decoded = json_decode($user->two_factor_recovery_codes, true);
        return is_array($decoded) ? $decoded : [];
    }
}
