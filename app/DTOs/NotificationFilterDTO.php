<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Channel;
use App\Enums\NotificationStatus;

final readonly class NotificationFilterDTO
{
    public function __construct(
        public string $subscriberId,
        public ?Channel $channel = null,
        public ?NotificationStatus $status = null,
        public int $perPage = 20,
    ) {}
}
