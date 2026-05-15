<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessBulkNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SendNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function send_dispatches_bulk_job_and_returns_accepted(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/notifications/send', [
            'channel' => 'sms',
            'message' => 'Your code is 1234',
            'recipient_ids' => ['sub_001', 'sub_002'],
            'priority' => 'transactional',
            'idempotency_key' => 'batch-test-001',
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'idempotency_key' => 'batch-test-001',
            'total_recipients' => 2,
            'priority' => 'transactional',
            'channel' => 'sms',
        ]);

        Queue::assertPushed(ProcessBulkNotificationJob::class);
    }

    #[Test]
    public function send_returns_validation_error_for_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/notifications/send', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'channel', 'message', 'recipient_ids', 'priority', 'idempotency_key',
        ]);
    }

    #[Test]
    public function send_rejects_invalid_channel(): void
    {
        $response = $this->postJson('/api/v1/notifications/send', [
            'channel' => 'telegram',
            'message' => 'Test',
            'recipient_ids' => ['sub_001'],
            'priority' => 'transactional',
            'idempotency_key' => 'key-001',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['channel']);
    }

    #[Test]
    public function send_rejects_invalid_priority(): void
    {
        $response = $this->postJson('/api/v1/notifications/send', [
            'channel' => 'sms',
            'message' => 'Test',
            'recipient_ids' => ['sub_001'],
            'priority' => 'urgent',
            'idempotency_key' => 'key-001',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
    }

    #[Test]
    public function send_rejects_empty_recipient_ids(): void
    {
        $response = $this->postJson('/api/v1/notifications/send', [
            'channel' => 'email',
            'message' => 'Test',
            'recipient_ids' => [],
            'priority' => 'marketing',
            'idempotency_key' => 'key-001',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recipient_ids']);
    }

    #[Test]
    public function send_returns_correct_json_structure(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/notifications/send', [
            'channel' => 'email',
            'message' => 'Welcome!',
            'recipient_ids' => ['sub_001'],
            'priority' => 'marketing',
            'idempotency_key' => 'struct-test',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'idempotency_key',
            'total_recipients',
            'priority',
            'channel',
        ]);

        $this->assertSame('marketing', $response->json('priority'));
        $this->assertSame('email', $response->json('channel'));
        $this->assertSame(1, $response->json('total_recipients'));
    }

    #[Test]
    public function send_returns_conflict_on_duplicate_idempotency_key(): void
    {
        Queue::fake();

        $payload = [
            'channel' => 'sms',
            'message' => 'Your code is 5678',
            'recipient_ids' => ['sub_001', 'sub_002'],
            'priority' => 'transactional',
            'idempotency_key' => 'batch-duplicate-test',
        ];

        $first = $this->postJson('/api/v1/notifications/send', $payload);
        $first->assertStatus(202);

        $second = $this->postJson('/api/v1/notifications/send', $payload);
        $second->assertStatus(409);
        $second->assertJson([
            'message' => 'Duplicate request',
            'idempotency_key' => 'batch-duplicate-test',
        ]);

        Queue::assertPushed(ProcessBulkNotificationJob::class, 1);
    }
}
