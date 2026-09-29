<?php

namespace App\Providers;

use App\Domains\Financial\Contracts\PaymentGatewayInterface;
use App\Domains\Financial\Services\Payment\SandboxPaymentGateway;
use App\Domains\Platform\Models\PlatformSetting;
use App\Domains\Tenancy\Services\CompanyContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CompanyContext::class);
        $this->app->bind(
            PaymentGatewayInterface::class,
            SandboxPaymentGateway::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        try {
            if (Schema::hasTable('platform_settings')) {
                PlatformSetting::applyMailSettings();
            }
        } catch (\Throwable $e) {
            // Ignore during early bootstrapping / migrations
        }

        RateLimiter::for('bingo.claim', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('bingo.daub', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('wallet.operations', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
    }
}
