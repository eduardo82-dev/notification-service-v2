<?php

declare(strict_types=1);

namespace App\Contracts\Gateways;

use App\Exceptions\GatewayFailureException;

interface NotificationGatewayInterface
{
    /**
     * @throws GatewayFailureException
     */
    public function send(string $recipientId, string $message): void;
}
