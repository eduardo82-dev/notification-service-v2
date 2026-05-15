<?php

declare(strict_types=1);

namespace App\Enums;

enum Priority: string
{
    case TRANSACTIONAL = 'transactional';
    case MARKETING = 'marketing';

    public function queueName(Channel $channel): string
    {
        $suffix = match ($this) {
            self::TRANSACTIONAL => 'high',
            self::MARKETING => 'low',
        };

        return "{$channel->value}_{$suffix}";
    }
}
