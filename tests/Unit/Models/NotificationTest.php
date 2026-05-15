<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use App\Models\Notification;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationTest extends TestCase
{
    #[Test]
    public function create_new_builds_notification_in_queued_state(): void
    {
        $notification = Notification::createNew(
            recipientId: 'sub_001',
            channel: Channel::SMS,
            message: 'Test message',
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: 'key-001',
        );

        $this->assertNotEmpty($notification->id);
        $this->assertSame('sub_001', $notification->recipient_id);
        $this->assertSame(Channel::SMS, $notification->channel);
        $this->assertSame('Test message', $notification->message);
        $this->assertSame(Priority::TRANSACTIONAL, $notification->priority);
        $this->assertSame(NotificationStatus::QUEUED, $notification->status);
        $this->assertSame('key-001', $notification->idempotency_key);
        $this->assertSame(0, $notification->attempts);
    }

    #[Test]
    public function mark_as_sent_transitions_from_queued(): void
    {
        $notification = $this->createNotification();

        $notification->markAsSent();

        $this->assertSame(NotificationStatus::SENT, $notification->status);
    }

    #[Test]
    public function mark_as_delivered_transitions_from_sent(): void
    {
        $notification = $this->createNotification();
        $notification->markAsSent();

        $notification->markAsDelivered();

        $this->assertSame(NotificationStatus::DELIVERED, $notification->status);
        $this->assertNotNull($notification->delivered_at);
    }

    #[Test]
    public function cannot_mark_delivered_notification_as_sent(): void
    {
        $notification = $this->createNotification();
        $notification->markAsSent();
        $notification->markAsDelivered();

        $this->expectException(InvalidArgumentException::class);
        $notification->markAsSent();
    }

    #[Test]
    public function cannot_mark_rejected_notification_as_delivered(): void
    {
        $notification = $this->createNotification();
        $notification->markAsRejected('Gateway timeout');

        $this->expectException(InvalidArgumentException::class);
        $notification->markAsDelivered();
    }

    #[Test]
    public function cannot_reject_delivered_notification(): void
    {
        $notification = $this->createNotification();
        $notification->markAsSent();
        $notification->markAsDelivered();

        $this->expectException(InvalidArgumentException::class);
        $notification->markAsRejected('Some error');
    }

    #[Test]
    public function mark_as_rejected_stores_reason(): void
    {
        $notification = $this->createNotification();

        $notification->markAsRejected('Invalid phone number');

        $this->assertSame(NotificationStatus::REJECTED, $notification->status);
        $this->assertSame('Invalid phone number', $notification->rejected_reason);
    }

    #[Test]
    public function increment_attempt_increases_counter(): void
    {
        $notification = $this->createNotification();

        $notification->incrementAttempt();
        $this->assertSame(1, $notification->attempts);

        $notification->incrementAttempt();
        $this->assertSame(2, $notification->attempts);
    }

    private function createNotification(): Notification
    {
        return Notification::createNew(
            recipientId: 'sub_001',
            channel: Channel::SMS,
            message: 'Test',
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: 'test-key',
        );
    }
}
