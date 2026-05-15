<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Channel;
use App\Enums\Priority;

final readonly class SendNotificationDTO
{
    /**
     * @param list<string> $recipientIds
     */
    public function __construct(
        public Channel $channel,
        public string $message,
        public array $recipientIds,
        public Priority $priority,
        public string $idempotencyKey,
    ) {}

    public function recipientIdempotencyKey(string $recipientId): string
    {
        return $this->idempotencyKey . ':' . $recipientId;
    }
}
