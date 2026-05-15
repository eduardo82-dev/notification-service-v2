<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\IdempotencyServiceInterface;

/**
 * Для тестов без Redis.
 */
final class InMemoryIdempotencyService implements IdempotencyServiceInterface
{
    /** @var array<string, true> */
    private array $keys = [];

    public function acquireLock(string $key): bool
    {
        if (isset($this->keys[$key])) {
            return false;
        }

        $this->keys[$key] = true;

        return true;
    }

    public function exists(string $key): bool
    {
        return isset($this->keys[$key]);
    }
}
