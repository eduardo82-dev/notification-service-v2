<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\NotificationStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NotificationStatusTest extends TestCase
{
    #[Test]
    public function delivered_is_final(): void
    {
        $this->assertTrue(NotificationStatus::DELIVERED->isFinal());
    }

    #[Test]
    public function rejected_is_final(): void
    {
        $this->assertTrue(NotificationStatus::REJECTED->isFinal());
    }

    #[Test]
    public function queued_is_not_final(): void
    {
        $this->assertFalse(NotificationStatus::QUEUED->isFinal());
    }

    #[Test]
    public function sent_is_not_final(): void
    {
        $this->assertFalse(NotificationStatus::SENT->isFinal());
    }
}
