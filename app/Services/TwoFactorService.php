<?php

namespace App\Services;

use App\Models\User;
use App\Models\SecurityLog;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TwoFactorService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate 2FA secret for user
     */
    public function generateSecret(User $user): string
    {
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        SecurityLog::logEvent('2fa_secret_generated', $user);

        return $secret;
    }

    /**
     * Generate QR code for 2FA setup
     */
    public function generateQrCode(User $user): string
    {
        $secret = $this->getDecryptedSecret($user);
        
        if (!$secret) {
            $secret = $this->generateSecret($user);
        }

        $companyName = $user->tenant?->name ?? config('app.name');
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            $companyName,
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        return $writer->writeString($qrCodeUrl);
    }

    /**
     * Verify 2FA code
     */
    public function verify(User $user, string $code): bool
    {
        $secret = $this->getDecryptedSecret($user);
        
        if (!$secret) {
            return false;
        }

        $isValid = $this->google2fa->verifyKey($secret, $code);

        SecurityLog::logEvent(
            '2fa_verification',
            $user,
            $isValid ? 'success' : 'failed',
            $isValid ? '2FA code verified successfully' : 'Invalid 2FA code provided'
        );

        return $isValid;
    }

    /**
     * Enable 2FA for user
     */
    public function enable(User $user, string $code): bool
    {
        if (!$this->verify($user, $code)) {
            return false;
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt($recoveryCodes->toJson()),
        ]);

        SecurityLog::logEvent('2fa_enabled', $user, 'success', '2FA authentication enabled');

        return true;
    }

    /**
     * Disable 2FA for user
     */
    public function disable(User $user): void
    {
        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_enabled' => false,
        ]);

        SecurityLog::logEvent('2fa_disabled', $user, 'success', '2FA authentication disabled');
    }

    /**
     * Generate recovery codes
     */
    public function generateRecoveryCodes(): Collection
    {
        return collect(range(1, 8))->map(function () {
            return Str::random(4) . '-' . Str::random(4);
        });
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(User $user): Collection
    {
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_recovery_codes' => encrypt($recoveryCodes->toJson()),
        ]);

        SecurityLog::logEvent('2fa_recovery_codes_regenerated', $user);

        return $recoveryCodes;
    }

    /**
     * Verify recovery code
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        if (!$user->two_factor_recovery_codes) {
            return false;
        }

        $recoveryCodes = collect(json_decode(decrypt($user->two_factor_recovery_codes), true));

        if (!$recoveryCodes->contains($code)) {
            SecurityLog::logEvent('2fa_recovery_failed', $user, 'failed', 'Invalid recovery code used');
            return false;
        }

        // Remove used recovery code
        $remainingCodes = $recoveryCodes->reject(fn($recoveryCode) => $recoveryCode === $code);

        $user->update([
            'two_factor_recovery_codes' => encrypt($remainingCodes->toJson()),
        ]);

        SecurityLog::logEvent('2fa_recovery_used', $user, 'success', 'Recovery code used successfully');

        return true;
    }

    /**
     * Get decrypted secret
     */
    protected function getDecryptedSecret(User $user): ?string
    {
        if (!$user->two_factor_secret) {
            return null;
        }

        try {
            return decrypt($user->two_factor_secret);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get recovery codes for display
     */
    public function getRecoveryCodes(User $user): Collection
    {
        if (!$user->two_factor_recovery_codes) {
            return collect();
        }

        try {
            return collect(json_decode(decrypt($user->two_factor_recovery_codes), true));
        } catch (\Exception $e) {
            return collect();
        }
    }
}