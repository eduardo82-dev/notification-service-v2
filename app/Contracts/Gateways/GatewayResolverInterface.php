<?php

declare(strict_types=1);

namespace App\Contracts\Gateways;

use App\Enums\Channel;

interface GatewayResolverInterface
{
    public function resolve(Channel $channel): NotificationGatewayInterface;
}
