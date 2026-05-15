<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessBulkNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PriorityQueueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function transactional_sms_dispatches_to_sms_high_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications/send', [
            'channel' => 'sms',
            'message' => 'Access code: 5678',
            'recipient_ids' => ['sub_001'],
            'priority' => 'transactional',
            'idempotency_key' => 'high-prio-test',
        ])->assertStatus(202);

        Queue::assertPushedOn('sms_high', ProcessBulkNotificationJob::class);
    }

    #[Test]
    public function marketing_email_dispatches_to_email_low_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications/send', [
            'channel' => 'email',
            'message' => 'Check out our sale!',
            'recipient_ids' => ['sub_001'],
            'priority' => 'marketing',
            'idempotency_key' => 'low-prio-test',
        ])->assertStatus(202);

        Queue::assertPushedOn('email_low', ProcessBulkNotificationJob::class);
    }
}
