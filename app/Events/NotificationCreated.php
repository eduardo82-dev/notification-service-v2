<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Notification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class NotificationCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Notification $notification,
    ) {}
}
