<?php

declare(strict_types=1);

namespace App\Gateways\Stub;

use App\Contracts\Gateways\EmailGatewayInterface;
use App\Exceptions\GatewayFailureException;
use Illuminate\Support\Facades\Log;
use Random\RandomException;

final class StubEmailGateway implements EmailGatewayInterface
{
    /**
     * @throws RandomException
     */
    public function send(string $recipientId, string $message): void
    {
        if (random_int(1, 10) === 1) {
            Log::warning('StubEmailGateway: simulated failure', compact('recipientId'));
            throw new GatewayFailureException('Simulated Email gateway timeout');
        }

        Log::info('StubEmailGateway: Email sent', compact('recipientId', 'message'));
    }
}
