<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Gateways\EmailGatewayInterface;
use App\Contracts\Gateways\GatewayResolverInterface;
use App\Contracts\Gateways\SmsGatewayInterface;
use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use App\Events\NotificationDelivered;
use App\Events\NotificationRejected;
use App\Events\NotificationSent;
use App\Exceptions\GatewayFailureException;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class JobProcessingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function job_processes_sms_and_updates_status_to_delivered(): void
    {
        Event::fake();

        $this->app->bind(SmsGatewayInterface::class, fn () => new class implements SmsGatewayInterface {
            public function send(string $recipientId, string $message): void {}
        });

        $notification = $this->createAndSaveNotification(Channel::SMS);

        $job = new SendNotificationJob($notification);
        $job->handle(
            app(GatewayResolverInterface::class),
            app(NotificationRepositoryInterface::class),
        );

        $notification->refresh();
        $this->assertSame(NotificationStatus::DELIVERED, $notification->status);
        $this->assertNotNull($notification->delivered_at);
        $this->assertSame(1, $notification->attempts);

        Event::assertDispatched(NotificationSent::class);
        Event::assertDispatched(NotificationDelivered::class);
    }

    #[Test]
    public function job_processes_email_and_updates_status_to_delivered(): void
    {
        Event::fake();

        $this->app->bind(EmailGatewayInterface::class, fn () => new class implements EmailGatewayInterface {
            public function send(string $recipientId, string $message): void {}
        });

        $notification = $this->createAndSaveNotification(Channel::EMAIL);

        $job = new SendNotificationJob($notification);
        $job->handle(
            app(GatewayResolverInterface::class),
            app(NotificationRepositoryInterface::class),
        );

        $notification->refresh();
        $this->assertSame(NotificationStatus::DELIVERED, $notification->status);
    }

    #[Test]
    public function job_skips_notification_in_final_state(): void
    {
        $notification = $this->createAndSaveNotification(Channel::SMS);
        $notification->markAsSent();
        $notification->markAsDelivered();
        $notification->save();

        $job = new SendNotificationJob($notification);
        $job->handle(
            app(GatewayResolverInterface::class),
            app(NotificationRepositoryInterface::class),
        );

        $notification->refresh();
        $this->assertSame(NotificationStatus::DELIVERED, $notification->status);
        $this->assertSame(0, $notification->attempts);
    }

    #[Test]
    public function job_rethrows_on_gateway_failure_for_retry(): void
    {
        $this->app->bind(SmsGatewayInterface::class, fn () => new class implements SmsGatewayInterface {
            public function send(string $recipientId, string $message): void
            {
                throw new GatewayFailureException('Connection refused');
            }
        });

        $notification = $this->createAndSaveNotification(Channel::SMS);

        $job = new SendNotificationJob($notification);
        $job->tries = 3;

        try {
            $job->handle(
                app(GatewayResolverInterface::class),
                app(NotificationRepositoryInterface::class),
            );
            $this->fail('Expected GatewayFailureException to be thrown');
        } catch (GatewayFailureException) {
            // Expected — Laravel will retry
        }

        $notification->refresh();
        $this->assertSame(1, $notification->attempts);
        $this->assertSame(NotificationStatus::QUEUED, $notification->status);
    }

    #[Test]
    public function job_marks_as_rejected_on_final_failure(): void
    {
        Event::fake();

        $this->app->bind(SmsGatewayInterface::class, fn () => new class implements SmsGatewayInterface {
            public function send(string $recipientId, string $message): void
            {
                throw new GatewayFailureException('Permanent failure');
            }
        });

        $notification = $this->createAndSaveNotification(Channel::SMS);

        $job = new SendNotificationJob($notification);
        $job->tries = 3;

        $job->failed(new GatewayFailureException('Permanent failure'));

        $notification->refresh();
        $this->assertSame(NotificationStatus::REJECTED, $notification->status);
        $this->assertSame('Permanent failure', $notification->rejected_reason);

        Event::assertDispatched(NotificationRejected::class);
    }

    private function createAndSaveNotification(Channel $channel): Notification
    {
        $notification = Notification::createNew(
            recipientId: 'sub_001',
            channel: $channel,
            message: 'Test message',
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: 'job-test-' . uniqid(),
        );
        $notification->save();

        return $notification;
    }
}
