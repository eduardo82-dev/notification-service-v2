<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Gateways\EmailGatewayInterface;
use App\Contracts\Gateways\GatewayResolverInterface;
use App\Contracts\Gateways\NotificationGatewayInterface;
use App\Contracts\Gateways\SmsGatewayInterface;
use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Contracts\Services\IdempotencyServiceInterface;
use App\Contracts\Services\NotificationServiceInterface;
use App\Events\NotificationCreated;
use App\Events\NotificationDelivered;
use App\Events\NotificationRejected;
use App\Events\NotificationSent;
use App\Gateways\Decorators\CircuitBreakerGatewayDecorator;
use App\Gateways\Decorators\RateLimitedGatewayDecorator;
use App\Gateways\GatewayResolver;
use App\Gateways\Stub\StubEmailGateway;
use App\Gateways\Stub\StubSmsGateway;
use App\Listeners\LogNotificationStatusChange;
use App\Repositories\EloquentNotificationRepository;
use App\Services\IdempotencyService;
use App\Services\InMemoryIdempotencyService;
use App\Services\NotificationService;
use Illuminate\Contracts\Redis\Connection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsGatewayInterface::class, StubSmsGateway::class);
        $this->app->bind(EmailGatewayInterface::class, StubEmailGateway::class);
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);
        $this->app->bind(NotificationServiceInterface::class, NotificationService::class);
        $this->app->bind(IdempotencyServiceInterface::class, function () {
            if ($this->app->environment('testing')) {
                return $this->app->make(InMemoryIdempotencyService::class);
            }

            return $this->app->make(IdempotencyService::class);
        });

        if ($this->app->environment('testing')) {
            $this->app->singleton(InMemoryIdempotencyService::class);
        }

        $this->app->singleton(GatewayResolverInterface::class, function () {
            $sms = $this->app->make(SmsGatewayInterface::class);
            $email = $this->app->make(EmailGatewayInterface::class);

            if (! $this->app->environment('testing')) {
                $redis = Redis::connection();
                $sms = $this->wrapWithDecorators($sms, $redis, 'sms');
                $email = $this->wrapWithDecorators($email, $redis, 'email');
            }

            return new GatewayResolver([
                'sms' => $sms,
                'email' => $email,
            ]);
        });
    }

    public function boot(): void
    {
        Event::listen(NotificationCreated::class, LogNotificationStatusChange::class);
        Event::listen(NotificationSent::class, LogNotificationStatusChange::class);
        Event::listen(NotificationDelivered::class, LogNotificationStatusChange::class);
        Event::listen(NotificationRejected::class, LogNotificationStatusChange::class);
    }

    private function wrapWithDecorators(
        NotificationGatewayInterface $gateway,
        Connection $redis,
        string $serviceName,
    ): NotificationGatewayInterface {
        $rateLimited = new RateLimitedGatewayDecorator(
            inner: $gateway,
            redis: $redis,
            serviceName: $serviceName,
            maxPerSecond: (int) config('notifications.rate_limiter.max_per_second', 100),
        );

        return new CircuitBreakerGatewayDecorator(
            inner: $rateLimited,
            redis: $redis,
            serviceName: $serviceName,
            failureThreshold: (int) config('notifications.circuit_breaker.failure_threshold', 5),
            cooldownSeconds: (int) config('notifications.circuit_breaker.cooldown_seconds', 30),
        );
    }
}
