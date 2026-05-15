<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\SendNotificationDTO;
use App\Enums\Channel;
use App\Enums\Priority;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SendNotificationDTOTest extends TestCase
{
    #[Test]
    public function recipient_idempotency_key_combines_batch_key_and_recipient(): void
    {
        $dto = new SendNotificationDTO(
            channel: Channel::SMS,
            message: 'Test',
            recipientIds: ['sub_001', 'sub_002'],
            priority: Priority::TRANSACTIONAL,
            idempotencyKey: 'batch-123',
        );

        $this->assertSame('batch-123:sub_001', $dto->recipientIdempotencyKey('sub_001'));
        $this->assertSame('batch-123:sub_002', $dto->recipientIdempotencyKey('sub_002'));
    }
}
