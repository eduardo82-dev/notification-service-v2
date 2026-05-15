<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Channel;
use App\Enums\Priority;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PriorityTest extends TestCase
{
    #[Test]
    public function transactional_sms_maps_to_sms_high(): void
    {
        $this->assertSame('sms_high', Priority::TRANSACTIONAL->queueName(Channel::SMS));
    }

    #[Test]
    public function marketing_sms_maps_to_sms_low(): void
    {
        $this->assertSame('sms_low', Priority::MARKETING->queueName(Channel::SMS));
    }

    #[Test]
    public function transactional_email_maps_to_email_high(): void
    {
        $this->assertSame('email_high', Priority::TRANSACTIONAL->queueName(Channel::EMAIL));
    }

    #[Test]
    public function marketing_email_maps_to_email_low(): void
    {
        $this->assertSame('email_low', Priority::MARKETING->queueName(Channel::EMAIL));
    }
}
