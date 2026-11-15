<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthentication
{
    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * The otpauth:// URL that authenticator apps read from the QR code.
     */
    public function otpAuthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->username ?: $user->email,
            $secret,
        );
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($this->otpAuthUrl($user, $secret));
    }

    /**
     * Verify a 6-digit code. Returns the accepted time-step on success (so the
     * caller can store it and reject replays), or null when the code is wrong.
     */
    public function verify(string $secret, string $code, ?int $lastTimestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        // A null "last timestamp" makes the library return `true` instead of the
        // time-step, which would break replay protection, so default it to 0.
        $result = $this->google2fa->verifyKeyNewer($secret, $code, $lastTimestamp ?? 0, 1);

        return $result === false ? null : (int) $result;
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    public function normalizeRecoveryCode(string $code): string
    {
        return Str::lower(trim($code));
    }
}
