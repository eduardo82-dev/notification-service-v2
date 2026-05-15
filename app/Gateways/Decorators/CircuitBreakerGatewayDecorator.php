<?php

declare(strict_types=1);

namespace App\Gateways\Decorators;

use App\Contracts\Gateways\NotificationGatewayInterface;
use App\Exceptions\GatewayFailureException;
use Illuminate\Contracts\Redis\Connection;

final class CircuitBreakerGatewayDecorator implements NotificationGatewayInterface
{
    private const string STATE_CLOSED = 'closed';
    private const string STATE_OPEN = 'open';
    private const string STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private readonly NotificationGatewayInterface $inner,
        private readonly Connection $redis,
        private readonly string $serviceName,
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 30,
    ) {}

    public function send(string $recipientId, string $message): void
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            if (! $this->cooldownExpired()) {
                throw new GatewayFailureException(
                    "Circuit breaker OPEN for [{$this->serviceName}]. Requests are blocked."
                );
            }

            $this->transitionTo(self::STATE_HALF_OPEN);
            $state = self::STATE_HALF_OPEN;
        }

        try {
            $this->inner->send($recipientId, $message);
        } catch (GatewayFailureException $e) {
            $this->recordFailure();

            if ($state === self::STATE_HALF_OPEN || $this->getFailureCount() >= $this->failureThreshold) {
                $this->transitionTo(self::STATE_OPEN);
            }

            throw $e;
        }

        if ($state === self::STATE_HALF_OPEN) {
            $this->reset();
        } else {
            $this->resetFailureCount();
        }
    }

    private function getState(): string
    {
        return $this->redis->get($this->key('state')) ?? self::STATE_CLOSED;
    }

    private function transitionTo(string $state): void
    {
        $this->redis->set($this->key('state'), $state);

        if ($state === self::STATE_OPEN) {
            $this->redis->set($this->key('opened_at'), (string) time());
        }
    }

    private function cooldownExpired(): bool
    {
        $openedAt = $this->redis->get($this->key('opened_at'));

        return $openedAt !== null && (time() - (int) $openedAt) >= $this->cooldownSeconds;
    }

    private function recordFailure(): void
    {
        $key = $this->key('failures');
        $this->redis->incr($key);
        $this->redis->command('expire', [$key, $this->cooldownSeconds * 2]);
    }

    private function getFailureCount(): int
    {
        return (int) ($this->redis->get($this->key('failures')) ?? 0);
    }

    private function resetFailureCount(): void
    {
        $this->redis->del($this->key('failures'));
    }

    private function reset(): void
    {
        $this->redis->del(
            $this->key('state'),
            $this->key('failures'),
            $this->key('opened_at'),
        );
    }

    private function key(string $suffix): string
    {
        return "circuit_breaker:{$this->serviceName}:{$suffix}";
    }
}
