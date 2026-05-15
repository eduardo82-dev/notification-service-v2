<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SubscriberNotificationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function returns_paginated_notifications_for_subscriber(): void
    {
        $this->createNotification('sub_001', Channel::SMS, NotificationStatus::QUEUED, 'key-1');
        $this->createNotification('sub_001', Channel::EMAIL, NotificationStatus::DELIVERED, 'key-2');
        $this->createNotification('sub_002', Channel::SMS, NotificationStatus::QUEUED, 'key-3');

        $response = $this->getJson('/api/v1/subscribers/sub_001/notifications');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                ['id', 'channel', 'recipient_id', 'status', 'created_at'],
            ],
        ]);
    }

    #[Test]
    public function filters_by_status(): void
    {
        $this->createNotification('sub_001', Channel::SMS, NotificationStatus::QUEUED, 'key-1');
        $this->createNotification('sub_001', Channel::SMS, NotificationStatus::DELIVERED, 'key-2');

        $response = $this->getJson('/api/v1/subscribers/sub_001/notifications?status=delivered');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame('delivered', $response->json('data.0.status'));
    }

    #[Test]
    public function filters_by_channel(): void
    {
        $this->createNotification('sub_001', Channel::SMS, NotificationStatus::QUEUED, 'key-1');
        $this->createNotification('sub_001', Channel::EMAIL, NotificationStatus::QUEUED, 'key-2');

        $response = $this->getJson('/api/v1/subscribers/sub_001/notifications?channel=email');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame('email', $response->json('data.0.channel'));
    }

    #[Test]
    public function returns_empty_list_for_unknown_subscriber(): void
    {
        $response = $this->getJson('/api/v1/subscribers/unknown/notifications');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function respects_per_page_parameter(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createNotification('sub_001', Channel::SMS, NotificationStatus::QUEUED, "key-{$i}");
        }

        $response = $this->getJson('/api/v1/subscribers/sub_001/notifications?per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    private function createNotification(
        string $recipientId,
        Channel $channel,
        NotificationStatus $status,
        string $idempotencyKey,
    ): Notification {
        $notification = Notification::createNew(
            recipientId: $recipientId,
            channel: $channel,
            message: 'Test message',
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: $idempotencyKey,
        );

        if ($status === NotificationStatus::SENT) {
            $notification->markAsSent();
        } elseif ($status === NotificationStatus::DELIVERED) {
            $notification->markAsSent();
            $notification->markAsDelivered();
        } elseif ($status === NotificationStatus::REJECTED) {
            $notification->markAsRejected('Test rejection');
        }

        $notification->save();

        return $notification;
    }
}
