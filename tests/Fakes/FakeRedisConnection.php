<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Closure;
use Illuminate\Contracts\Redis\Connection;

final class FakeRedisConnection implements Connection
{
    private array $store = [];

    public function subscribe($channels, Closure $callback): void {}

    public function psubscribe($channels, Closure $callback): void {}

    public function command($method, array $parameters = []): mixed
    {
        return match ($method) {
            'expire' => true,
            default => $this->$method(...$parameters),
        };
    }

    public function get(string $key): ?string
    {
        return $this->store[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->store[$key] = $value;
    }

    public function incr(string $key): int
    {
        $current = (int) ($this->store[$key] ?? 0);
        $this->store[$key] = (string) ++$current;

        return $current;
    }

    public function del(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($this->store[$key]);
        }
    }
}
