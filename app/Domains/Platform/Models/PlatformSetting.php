<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class PlatformSetting extends Model
{
    use HasFactory;

    protected $table = 'platform_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Retrieve a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Store or update a setting value by key.
     */
    public static function set(string $key, mixed $value): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Get email configuration with fallback to environment defaults.
     *
     * @return array<string, mixed>
     */
    public static function getMailSettings(): array
    {
        $stored = static::get('mail_settings', []);

        return [
            'driver' => $stored['driver'] ?? config('mail.default', 'log'),
            'host' => $stored['host'] ?? config('mail.mailers.smtp.host', '127.0.0.1'),
            'port' => (int) ($stored['port'] ?? config('mail.mailers.smtp.port', 587)),
            'encryption' => $stored['encryption'] ?? 'tls',
            'username' => $stored['username'] ?? config('mail.mailers.smtp.username', ''),
            'password' => $stored['password'] ?? '',
            'from_address' => $stored['from_address'] ?? config('mail.from.address', 'noreply@nobingo.live'),
            'from_name' => $stored['from_name'] ?? config('mail.from.name', 'Nobingo Platform'),
            'resend_api_key' => $stored['resend_api_key'] ?? config('services.resend.key', ''),
            'has_password' => ! empty($stored['password']),
        ];
    }

    /**
     * Dynamically override Laravel mail configuration in runtime.
     */
    public static function applyMailSettings(): void
    {
        $stored = static::get('mail_settings');

        if (! is_array($stored) || empty($stored)) {
            return;
        }

        $driver = $stored['driver'] ?? 'log';
        Config::set('mail.default', $driver);

        if (! empty($stored['from_address'])) {
            Config::set('mail.from.address', $stored['from_address']);
        }

        if (! empty($stored['from_name'])) {
            Config::set('mail.from.name', $stored['from_name']);
        }

        if ($driver === 'smtp') {
            Config::set('mail.mailers.smtp.host', $stored['host'] ?? '127.0.0.1');
            Config::set('mail.mailers.smtp.port', (int) ($stored['port'] ?? 587));
            Config::set('mail.mailers.smtp.username', $stored['username'] ?? null);

            if (! empty($stored['password'])) {
                Config::set('mail.mailers.smtp.password', $stored['password']);
            }

            $encryption = $stored['encryption'] ?? 'tls';
            Config::set('mail.mailers.smtp.scheme', $encryption === 'none' ? null : $encryption);
        } elseif ($driver === 'resend') {
            if (! empty($stored['resend_api_key'])) {
                Config::set('services.resend.key', $stored['resend_api_key']);
                Config::set('resend.api_key', $stored['resend_api_key']);
            }
        }
    }

    /**
     * Get platform revenue sharing settings.
     *
     * @return array{winner_share_percentage: float, platform_fee_percentage: float}
     */
    public static function getRevenueSettings(): array
    {
        $stored = static::get('revenue_settings', []);

        return [
            'winner_share_percentage' => (float) ($stored['winner_share_percentage'] ?? 75.0),
            'platform_fee_percentage' => (float) ($stored['platform_fee_percentage'] ?? 20.0),
        ];
    }
}
