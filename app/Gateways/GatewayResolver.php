<?php

declare(strict_types=1);

namespace App\Gateways;

use App\Contracts\Gateways\GatewayResolverInterface;
use App\Contracts\Gateways\NotificationGatewayInterface;
use App\Enums\Channel;

final readonly class GatewayResolver implements GatewayResolverInterface
{
    /**
     * @param array<string, NotificationGatewayInterface> $gateways
     */
    public function __construct(
        private array $gateways,
    ) {}

    public function resolve(Channel $channel): NotificationGatewayInterface
    {
        return $this->gateways[$channel->value]
            ?? throw new \InvalidArgumentException("No gateway registered for channel [{$channel->value}].");
    }
}
