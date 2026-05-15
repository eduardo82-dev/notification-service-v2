<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\IdempotencyServiceInterface;
use Illuminate\Contracts\Redis\Connection;

final class IdempotencyService implements IdempotencyServiceInterface
{
    private const string PREFIX = 'idemp:';

    public function __construct(
        private readonly Connection $redis,
    ) {}

    public function acquireLock(string $key): bool
    {
        $ttl = (int) config('notifications.idempotency_ttl', 86400);

        $result = $this->redis->command('set', [
            self::PREFIX . $key,
            '1',
            'EX',
            $ttl,
            'NX',
        ]);

        return $result !== null;
    }

    public function exists(string $key): bool
    {
        return (bool) $this->redis->command('exists', [self::PREFIX . $key]);
    }
}
