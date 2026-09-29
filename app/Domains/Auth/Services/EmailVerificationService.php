<?php

namespace App\Domains\Auth\Services;

use App\Domains\Tenancy\Models\Company;
use App\Mail\CompanyAdminWelcomeMail;
use App\Mail\CompanyPasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailVerificationService
{
    public const ACTION_WELCOME_VERIFICATION = 'welcome_verification';

    public const ACTION_PASSWORD_RESET = 'password_reset';

    public const TOKEN_EXPIRY_MINUTES = 15;

    /**
     * Generate a 6-digit OTP code and signed URL for email verification or password reset.
     *
     * @return array{otp: string, token: string, url: string}
     */
    public function generateToken(User $user, string $action): array
    {
        $otp = sprintf('%06d', random_int(100000, 999999));
        $token = bin2hex(random_bytes(24));

        $cacheKey = $this->getCacheKey($user->id, $action);
        Cache::put($cacheKey, [
            'otp' => $otp,
            'token_hash' => hash('sha256', $token),
            'user_id' => $user->id,
            'action' => $action,
        ], now()->addMinutes(self::TOKEN_EXPIRY_MINUTES));

        $url = URL::temporarySignedRoute(
            'platform.verify-email',
            now()->addMinutes(self::TOKEN_EXPIRY_MINUTES),
            [
                'user' => $user->id,
                'token' => $token,
                'action' => $action,
            ]
        );

        return [
            'otp' => $otp,
            'token' => $token,
            'url' => $url,
        ];
    }

    /**
     * Send welcome verification email to a newly provisioned company admin.
     */
    public function sendWelcomeVerificationEmail(User $user, Company $company, ?string $temporaryPassword = null): void
    {
        $payload = $this->generateToken($user, self::ACTION_WELCOME_VERIFICATION);

        Mail::to($user->email)->send(new CompanyAdminWelcomeMail(
            adminUser: $user,
            company: $company,
            otp: $payload['otp'],
            verificationUrl: $payload['url'],
            temporaryPassword: $temporaryPassword
        ));
    }

    /**
     * Send password reset verification email with 6-digit OTP code.
     */
    public function sendPasswordResetOtpEmail(User $user, Company $company): string
    {
        $payload = $this->generateToken($user, self::ACTION_PASSWORD_RESET);

        Mail::to($user->email)->send(new CompanyPasswordResetMail(
            adminUser: $user,
            company: $company,
            otp: $payload['otp'],
            resetUrl: $payload['url']
        ));

        return $payload['otp'];
    }

    /**
     * Validate a 6-digit OTP code for a user and action.
     */
    public function verifyOtp(User $user, string $otp, string $action): bool
    {
        $cacheKey = $this->getCacheKey($user->id, $action);
        $data = Cache::get($cacheKey);

        if (! is_array($data)) {
            return false;
        }

        if (hash_equals((string) $data['otp'], trim($otp))) {
            Cache::forget($cacheKey);

            return true;
        }

        return false;
    }

    /**
     * Validate a secret token for a user and action.
     */
    public function verifyToken(User $user, string $token, string $action): bool
    {
        $cacheKey = $this->getCacheKey($user->id, $action);
        $data = Cache::get($cacheKey);

        if (! is_array($data)) {
            return false;
        }

        if (hash_equals($data['token_hash'], hash('sha256', $token))) {
            Cache::forget($cacheKey);

            return true;
        }

        return false;
    }

    /**
     * Mark a user's email address as verified.
     */
    public function markEmailAsVerified(User $user): void
    {
        if ($user->email_verified_at === null) {
            $user->update(['email_verified_at' => now()]);
        }
    }

    protected function getCacheKey(int $userId, string $action): string
    {
        return "nobingo_auth_otp_{$action}_{$userId}";
    }
}
