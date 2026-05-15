<?php

declare(strict_types=1);

namespace Tests\Unit\Gateways\Decorators;

use App\Contracts\Gateways\NotificationGatewayInterface;
use App\Exceptions\GatewayFailureException;
use App\Gateways\Decorators\CircuitBreakerGatewayDecorator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Fakes\FakeRedisConnection;
use Tests\TestCase;

final class CircuitBreakerGatewayDecoratorTest extends TestCase
{
    private NotificationGatewayInterface&MockObject $inner;
    private CircuitBreakerGatewayDecorator $decorator;
    private FakeRedisConnection $redis;

    private const int FAILURE_THRESHOLD = 3;
    private const int COOLDOWN_SECONDS = 5;
    private const string SERVICE_NAME = 'test_gateway';

    protected function setUp(): void
    {
        parent::setUp();

        $this->redis = new FakeRedisConnection();
        $this->inner = $this->createMock(NotificationGatewayInterface::class);
        $this->decorator = new CircuitBreakerGatewayDecorator(
            inner: $this->inner,
            redis: $this->redis,
            serviceName: self::SERVICE_NAME,
            failureThreshold: self::FAILURE_THRESHOLD,
            cooldownSeconds: self::COOLDOWN_SECONDS,
        );
    }

    #[Test]
    public function closed_state_passes_requests_through(): void
    {
        $this->inner->expects($this->once())
            ->method('send')
            ->with('recipient_1', 'hello');

        $this->decorator->send('recipient_1', 'hello');
    }

    #[Test]
    public function single_failure_does_not_open_circuit(): void
    {
        $callCount = 0;
        $this->inner->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(function () use (&$callCount): void {
                $callCount++;

                if ($callCount === 1) {
                    throw new GatewayFailureException();
                }
            });

        try {
            $this->decorator->send('r1', 'msg');
        } catch (GatewayFailureException) {
        }

        // Second call should still go through (circuit is closed)
        $this->decorator->send('r2', 'msg');
    }

    #[Test]
    public function opens_after_reaching_failure_threshold(): void
    {
        $this->inner->method('send')
            ->willThrowException(new GatewayFailureException());

        for ($i = 0; $i < self::FAILURE_THRESHOLD; $i++) {
            try {
                $this->decorator->send("r{$i}", 'msg');
            } catch (GatewayFailureException) {
            }
        }

        // Next call should be rejected without hitting the inner gateway
        $this->inner = $this->createMock(NotificationGatewayInterface::class);
        $this->inner->expects($this->never())->method('send');

        $decorator = new CircuitBreakerGatewayDecorator(
            inner: $this->inner,
            redis: $this->redis,
            serviceName: self::SERVICE_NAME,
            failureThreshold: self::FAILURE_THRESHOLD,
            cooldownSeconds: self::COOLDOWN_SECONDS,
        );

        $this->expectException(GatewayFailureException::class);
        $this->expectExceptionMessage('Circuit breaker OPEN');

        $decorator->send('r_blocked', 'msg');
    }

    #[Test]
    public function rejects_requests_while_open(): void
    {
        $this->tripCircuit();

        $this->inner->expects($this->never())->method('send');

        $this->expectException(GatewayFailureException::class);
        $this->expectExceptionMessage('Circuit breaker OPEN');

        $this->decorator->send('r1', 'msg');
    }

    #[Test]
    public function transitions_to_half_open_after_cooldown(): void
    {
        $this->tripCircuit();

        // Simulate cooldown expiry by backdating opened_at
        $this->redis->set(
            "circuit_breaker:" . self::SERVICE_NAME . ":opened_at",
            (string) (time() - self::COOLDOWN_SECONDS - 1),
        );

        // Inner should be called (half-open allows one probe request)
        $this->inner->expects($this->once())
            ->method('send')
            ->with('r_probe', 'msg');

        $this->decorator->send('r_probe', 'msg');
    }

    #[Test]
    public function closes_circuit_on_successful_half_open_request(): void
    {
        $this->tripCircuit();

        // Simulate cooldown expiry
        $this->redis->set(
            "circuit_breaker:" . self::SERVICE_NAME . ":opened_at",
            (string) (time() - self::COOLDOWN_SECONDS - 1),
        );

        // Successful half-open probe
        $this->decorator->send('r_probe', 'msg');

        // Circuit should be closed now — state key should be cleared
        $state = $this->redis->get("circuit_breaker:" . self::SERVICE_NAME . ":state");
        $this->assertNull($state, 'Circuit should be reset to closed after successful half-open probe');
    }

    #[Test]
    public function reopens_on_failure_in_half_open_state(): void
    {
        $this->tripCircuit();

        // Simulate cooldown expiry
        $this->redis->set(
            "circuit_breaker:" . self::SERVICE_NAME . ":opened_at",
            (string) (time() - self::COOLDOWN_SECONDS - 1),
        );

        // Half-open probe fails
        $this->inner->method('send')
            ->willThrowException(new GatewayFailureException());

        try {
            $this->decorator->send('r_probe', 'msg');
        } catch (GatewayFailureException) {
        }

        // Should be open again
        $state = $this->redis->get("circuit_breaker:" . self::SERVICE_NAME . ":state");
        $this->assertSame('open', $state);
    }

    #[Test]
    public function successful_request_resets_failure_count(): void
    {
        $callCount = 0;
        $this->inner->method('send')
            ->willReturnCallback(function () use (&$callCount): void {
                $callCount++;

                // Calls 1,2 fail; call 3 succeeds; call 4 fails; call 5 succeeds
                if (in_array($callCount, [1, 2, 4], true)) {
                    throw new GatewayFailureException();
                }
            });

        // 2 failures
        for ($i = 0; $i < 2; $i++) {
            try {
                $this->decorator->send("r{$i}", 'msg');
            } catch (GatewayFailureException) {
            }
        }

        // 1 success — resets counter
        $this->decorator->send('r_ok', 'msg');

        // 1 more failure — should NOT trip (counter was reset)
        try {
            $this->decorator->send('r_fail', 'msg');
        } catch (GatewayFailureException) {
        }

        // Should still be able to send (circuit closed)
        $this->decorator->send('r_ok2', 'msg');

        $this->assertSame(5, $callCount);
    }

    private function tripCircuit(): void
    {
        $this->inner->method('send')
            ->willThrowException(new GatewayFailureException());

        for ($i = 0; $i < self::FAILURE_THRESHOLD; $i++) {
            try {
                $this->decorator->send("r{$i}", 'msg');
            } catch (GatewayFailureException) {
            }
        }

        // Recreate with fresh mock for subsequent assertions
        $this->inner = $this->createMock(NotificationGatewayInterface::class);
        $this->decorator = new CircuitBreakerGatewayDecorator(
            inner: $this->inner,
            redis: $this->redis,
            serviceName: self::SERVICE_NAME,
            failureThreshold: self::FAILURE_THRESHOLD,
            cooldownSeconds: self::COOLDOWN_SECONDS,
        );
    }
}
