<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Contracts\Services\IdempotencyServiceInterface;
use App\DTOs\SendNotificationDTO;
use App\Enums\Channel;
use App\Enums\Priority;
use App\Jobs\ProcessBulkNotificationJob;
use App\Jobs\SendNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProcessBulkNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creates_notifications_and_dispatches_send_jobs(): void
    {
        Queue::fake([SendNotificationJob::class]);

        $dto = new SendNotificationDTO(
            channel: Channel::SMS,
            message: 'Your code is 1234',
            recipientIds: ['sub_001', 'sub_002'],
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: 'batch-test-001',
        );

        $job = new ProcessBulkNotificationJob($dto);
        $job->handle(
            app(NotificationRepositoryInterface::class),
            app(IdempotencyServiceInterface::class),
        );

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => 'sub_001',
            'channel' => 'sms',
            'status' => 'queued',
            'idempotency_key' => 'batch-test-001:sub_001',
        ]);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => 'sub_002',
            'channel' => 'sms',
            'status' => 'queued',
            'idempotency_key' => 'batch-test-001:sub_002',
        ]);

        Queue::assertPushed(SendNotificationJob::class, 2);
        Queue::assertPushedOn('sms_high', SendNotificationJob::class);
    }

    #[Test]
    public function skips_duplicate_recipients_via_idempotency(): void
    {
        Queue::fake([SendNotificationJob::class]);

        $dto = new SendNotificationDTO(
            channel: Channel::EMAIL,
            message: 'Hello',
            recipientIds: ['sub_001'],
            priority: Priority::MARKETING,
            idempotencyKey: 'idemp-dup-test',
        );

        $job = new ProcessBulkNotificationJob($dto);
        $repository = app(NotificationRepositoryInterface::class);
        $idempotency = app(IdempotencyServiceInterface::class);

        // First run
        $job->handle($repository, $idempotency);
        $this->assertDatabaseCount('notifications', 1);

        // Second run with same key
        $job->handle($repository, $idempotency);
        $this->assertDatabaseCount('notifications', 1);

        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    #[Test]
    public function different_idempotency_keys_create_separate_notifications(): void
    {
        Queue::fake([SendNotificationJob::class]);

        $repository = app(NotificationRepositoryInterface::class);
        $idempotency = app(IdempotencyServiceInterface::class);

        $dto1 = new SendNotificationDTO(
            channel: Channel::SMS,
            message: 'First',
            recipientIds: ['sub_001'],
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: 'key-a',
        );

        $dto2 = new SendNotificationDTO(
            channel: Channel::SMS,
            message: 'Second',
            recipientIds: ['sub_001'],
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: 'key-b',
        );

        (new ProcessBulkNotificationJob($dto1))->handle($repository, $idempotency);
        (new ProcessBulkNotificationJob($dto2))->handle($repository, $idempotency);

        $this->assertDatabaseCount('notifications', 2);
    }

    #[Test]
    public function same_batch_key_with_different_recipients_creates_all(): void
    {
        Queue::fake([SendNotificationJob::class]);

        $dto = new SendNotificationDTO(
            channel: Channel::SMS,
            message: 'Batch test',
            recipientIds: ['sub_001', 'sub_002', 'sub_003'],
            priority: Priority::MARKETING,
            idempotencyKey: 'batch-multi',
        );

        $job = new ProcessBulkNotificationJob($dto);
        $job->handle(
            app(NotificationRepositoryInterface::class),
            app(IdempotencyServiceInterface::class),
        );

        $this->assertDatabaseCount('notifications', 3);
        Queue::assertPushedOn('sms_low', SendNotificationJob::class);
    }

    #[Test]
    public function dispatches_send_jobs_to_correct_priority_queue(): void
    {
        Queue::fake([SendNotificationJob::class]);

        $repository = app(NotificationRepositoryInterface::class);
        $idempotency = app(IdempotencyServiceInterface::class);

        $transactional = new SendNotificationDTO(
            channel: Channel::SMS,
            message: 'Code: 5678',
            recipientIds: ['sub_001'],
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: 'high-prio',
        );

        (new ProcessBulkNotificationJob($transactional))->handle($repository, $idempotency);
        Queue::assertPushedOn('sms_high', SendNotificationJob::class);
    }
}
