<?php

declare(strict_types=1);

namespace App\Gateways\Decorators;

use App\Contracts\Gateways\NotificationGatewayInterface;
use App\Exceptions\GatewayFailureException;
use Illuminate\Contracts\Redis\Connection;

final class RateLimitedGatewayDecorator implements NotificationGatewayInterface
{
    public function __construct(
        private readonly NotificationGatewayInterface $inner,
        private readonly Connection $redis,
        private readonly string $serviceName,
        private readonly int $maxPerSecond = 100,
    ) {}

    public function send(string $recipientId, string $message): void
    {
        if (! $this->acquireToken()) {
            throw new GatewayFailureException(
                "Rate limit exceeded for [{$this->serviceName}]: max {$this->maxPerSecond} req/s."
            );
        }

        $this->inner->send($recipientId, $message);
    }

    private function acquireToken(): bool
    {
        $key = "rate_limiter:{$this->serviceName}";
        $now = time();
        $windowKey = "{$key}:{$now}";

        $current = (int) ($this->redis->get($windowKey) ?? 0);

        if ($current >= $this->maxPerSecond) {
            return false;
        }

        $this->redis->incr($windowKey);
        $this->redis->command('expire', [$windowKey, 2]);

        return true;
    }
}
