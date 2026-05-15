<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface IdempotencyServiceInterface
{
    public function acquireLock(string $key): bool;

    public function exists(string $key): bool;
}
