<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\NotificationChannel;
use App\Services\Webhook\ProviderSignatureService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProviderSignatureService::class, function () {
            $secret = (string) config('notifications.webhook.secret', '');

            if ($secret === '') {
                throw new RuntimeException('PROVIDER_WEBHOOK_SECRET is not configured.');
            }

            return new ProviderSignatureService($secret);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (NotificationChannel::cases() as $channel) {
            RateLimiter::for("gateway:{$channel->value}", fn () => Limit::perSecond(
                (int) config("notifications.gateway.rate_limit.{$channel->value}")
            ));
        }
    }
}
