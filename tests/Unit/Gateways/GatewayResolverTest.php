<?php

declare(strict_types=1);

namespace Tests\Unit\Gateways;

use App\Contracts\Gateways\NotificationGatewayInterface;
use App\Enums\Channel;
use App\Gateways\GatewayResolver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GatewayResolverTest extends TestCase
{
    #[Test]
    public function resolves_sms_gateway(): void
    {
        $sms = $this->createStub(NotificationGatewayInterface::class);
        $email = $this->createStub(NotificationGatewayInterface::class);

        $resolver = new GatewayResolver(['sms' => $sms, 'email' => $email]);

        $this->assertSame($sms, $resolver->resolve(Channel::SMS));
    }

    #[Test]
    public function resolves_email_gateway(): void
    {
        $sms = $this->createStub(NotificationGatewayInterface::class);
        $email = $this->createStub(NotificationGatewayInterface::class);

        $resolver = new GatewayResolver(['sms' => $sms, 'email' => $email]);

        $this->assertSame($email, $resolver->resolve(Channel::EMAIL));
    }
}
