<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Gateways\GatewayResolverInterface;
use App\Contracts\Gateways\SmsGatewayInterface;
use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Contracts\Services\IdempotencyServiceInterface;
use App\DTOs\SendNotificationDTO;
use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use App\Jobs\ProcessBulkNotificationJob;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function bulk_job_creates_queued_audit_log_entry(): void
    {
        Queue::fake([SendNotificationJob::class]);

        $dto = new SendNotificationDTO(
            channel: Channel::SMS,
            message: 'Test',
            recipientIds: ['sub_001'],
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: 'log-test-001',
        );

        (new ProcessBulkNotificationJob($dto))->handle(
            app(NotificationRepositoryInterface::class),
            app(IdempotencyServiceInterface::class),
        );

        $this->assertDatabaseHas('notification_logs', [
            'status' => 'queued',
            'previous_status' => null,
        ]);
    }

    #[Test]
    public function job_processing_creates_full_audit_trail(): void
    {
        $this->app->bind(SmsGatewayInterface::class, fn () => new class implements SmsGatewayInterface {
            public function send(string $recipientId, string $message): void {}
        });

        $notification = Notification::createNew(
            recipientId: 'sub_001',
            channel: Channel::SMS,
            message: 'Audit trail test',
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: 'audit-trail-test',
        );
        $notification->save();

        NotificationLog::createEntry($notification, NotificationStatus::QUEUED);

        $job = new SendNotificationJob($notification);
        $job->handle(
            app(GatewayResolverInterface::class),
            app(NotificationRepositoryInterface::class),
        );

        $logs = NotificationLog::where('notification_id', $notification->id)
            ->orderBy('id')
            ->get();

        $statuses = $logs->pluck('status')->map(fn ($s) => $s->value)->toArray();
        $this->assertContains('queued', $statuses);
        $this->assertContains('sent', $statuses);
        $this->assertContains('delivered', $statuses);

        $sentLog = $logs->firstWhere('status', NotificationStatus::SENT);
        $this->assertSame(NotificationStatus::QUEUED, $sentLog->previous_status);

        $deliveredLog = $logs->firstWhere('status', NotificationStatus::DELIVERED);
        $this->assertSame(NotificationStatus::SENT, $deliveredLog->previous_status);
    }

    #[Test]
    public function rejected_notification_log_contains_reason_in_context(): void
    {
        $notification = Notification::createNew(
            recipientId: 'sub_001',
            channel: Channel::SMS,
            message: 'Reject test',
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: 'reject-log-test',
        );
        $notification->save();

        $job = new SendNotificationJob($notification);
        $job->failed(new \App\Exceptions\GatewayFailureException('Connection refused'));

        $log = NotificationLog::where('notification_id', $notification->id)
            ->where('status', 'rejected')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Connection refused', $log->context['rejected_reason']);
    }

    #[Test]
    public function notification_has_logs_relation(): void
    {
        Queue::fake([SendNotificationJob::class]);

        $dto = new SendNotificationDTO(
            channel: Channel::EMAIL,
            message: 'Relation test',
            recipientIds: ['sub_001'],
            priority: Priority::MARKETING,
            idempotencyKey: 'relation-test',
        );

        (new ProcessBulkNotificationJob($dto))->handle(
            app(NotificationRepositoryInterface::class),
            app(IdempotencyServiceInterface::class),
        );

        $notification = Notification::first();
        $this->assertTrue($notification->logs->isNotEmpty());
        $this->assertSame(NotificationStatus::QUEUED, $notification->logs->first()->status);
    }
}
