<?php

declare(strict_types=1);

namespace App\Gateways\Stub;

use App\Contracts\Gateways\SmsGatewayInterface;
use App\Exceptions\GatewayFailureException;
use Illuminate\Support\Facades\Log;
use Random\RandomException;

final class StubSmsGateway implements SmsGatewayInterface
{
    /**
     * @throws RandomException
     */
    public function send(string $recipientId, string $message): void
    {
        if (random_int(1, 10) === 1) {
            Log::warning('StubSmsGateway: simulated failure', compact('recipientId'));
            throw new GatewayFailureException('Simulated SMS gateway timeout');
        }

        Log::info('StubSmsGateway: SMS sent', compact('recipientId', 'message'));
    }
}
