<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class NotificationNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Notification [{$id}] not found", 404);
    }
}
