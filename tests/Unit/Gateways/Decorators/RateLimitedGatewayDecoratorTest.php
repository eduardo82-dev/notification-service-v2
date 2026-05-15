<?php

declare(strict_types=1);

namespace Tests\Unit\Gateways\Decorators;

use App\Contracts\Gateways\NotificationGatewayInterface;
use App\Exceptions\GatewayFailureException;
use App\Gateways\Decorators\RateLimitedGatewayDecorator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Fakes\FakeRedisConnection;
use Tests\TestCase;

final class RateLimitedGatewayDecoratorTest extends TestCase
{
    private NotificationGatewayInterface&MockObject $inner;
    private FakeRedisConnection $redis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redis = new FakeRedisConnection();
        $this->inner = $this->createMock(NotificationGatewayInterface::class);
    }

    #[Test]
    public function allows_requests_under_limit(): void
    {
        $decorator = $this->makeDecorator(maxPerSecond: 5);

        $this->inner->expects($this->exactly(5))
            ->method('send');

        for ($i = 0; $i < 5; $i++) {
            $decorator->send("r{$i}", 'msg');
        }
    }

    #[Test]
    public function blocks_requests_when_limit_exceeded(): void
    {
        $decorator = $this->makeDecorator(maxPerSecond: 3);

        $this->inner->expects($this->exactly(3))
            ->method('send');

        for ($i = 0; $i < 3; $i++) {
            $decorator->send("r{$i}", 'msg');
        }

        $this->expectException(GatewayFailureException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $decorator->send('r_blocked', 'msg');
    }

    #[Test]
    public function does_not_block_inner_gateway_exceptions(): void
    {
        $decorator = $this->makeDecorator(maxPerSecond: 10);

        $this->inner->method('send')
            ->willThrowException(new GatewayFailureException('Provider down'));

        $this->expectException(GatewayFailureException::class);
        $this->expectExceptionMessage('Provider down');

        $decorator->send('r1', 'msg');
    }

    #[Test]
    public function separate_service_names_have_independent_limits(): void
    {
        $smsDecorator = $this->makeDecorator(maxPerSecond: 2, serviceName: 'sms');
        $emailDecorator = $this->makeDecorator(maxPerSecond: 2, serviceName: 'email');

        $this->inner->expects($this->exactly(4))
            ->method('send');

        // Exhaust SMS limit
        $smsDecorator->send('r1', 'msg');
        $smsDecorator->send('r2', 'msg');

        // Email should still work
        $emailDecorator->send('r1', 'msg');
        $emailDecorator->send('r2', 'msg');
    }

    #[Test]
    public function failed_requests_still_count_toward_limit(): void
    {
        $decorator = $this->makeDecorator(maxPerSecond: 2);

        $this->inner->method('send')
            ->willThrowException(new GatewayFailureException('Provider down'));

        // 2 failed requests consume the quota
        for ($i = 0; $i < 2; $i++) {
            try {
                $decorator->send("r{$i}", 'msg');
            } catch (GatewayFailureException) {
            }
        }

        // 3rd should be rate-limited (not provider error)
        $this->expectException(GatewayFailureException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $decorator->send('r_blocked', 'msg');
    }

    private function makeDecorator(int $maxPerSecond, string $serviceName = 'test_gateway'): RateLimitedGatewayDecorator
    {
        return new RateLimitedGatewayDecorator(
            inner: $this->inner,
            redis: $this->redis,
            serviceName: $serviceName,
            maxPerSecond: $maxPerSecond,
        );
    }
}
