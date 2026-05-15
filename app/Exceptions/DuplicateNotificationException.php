<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class DuplicateNotificationException extends RuntimeException
{
    public function __construct(
        public readonly string $idempotencyKey,
        string $message = 'Duplicate request detected',
    ) {
        parent::__construct($message, 409);
    }
}
