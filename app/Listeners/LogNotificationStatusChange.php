<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationStatus;
use App\Events\NotificationCreated;
use App\Events\NotificationDelivered;
use App\Events\NotificationRejected;
use App\Events\NotificationSent;
use App\Models\NotificationLog;

final class LogNotificationStatusChange
{
    private const array PREVIOUS_STATUS_MAP = [
        NotificationCreated::class => null,
        NotificationSent::class => NotificationStatus::QUEUED,
        NotificationDelivered::class => NotificationStatus::SENT,
        NotificationRejected::class => null,
    ];

    public function handle(
        NotificationCreated|NotificationSent|NotificationDelivered|NotificationRejected $event,
    ): void {
        $notification = $event->notification;

        $previousStatus = self::PREVIOUS_STATUS_MAP[$event::class] ?? null;

        $context = [];
        if ($event instanceof NotificationRejected && $notification->rejected_reason) {
            $context['rejected_reason'] = $notification->rejected_reason;
        }
        if ($notification->attempts > 0) {
            $context['attempt'] = $notification->attempts;
        }

        NotificationLog::createEntry(
            notification: $notification,
            status: $notification->status,
            previousStatus: $previousStatus,
            context: $context ?: null,
        );
    }
}
