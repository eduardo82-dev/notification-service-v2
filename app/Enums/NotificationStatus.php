<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case REJECTED = 'rejected';

    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::REJECTED], true);
    }
}
