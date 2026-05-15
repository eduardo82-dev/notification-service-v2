<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Redis\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class HealthController extends Controller
{
    #[OA\Get(
        path: '/api/health',
        description: 'Returns the health status of PostgreSQL, Redis, and RabbitMQ with latency measurements.',
        summary: 'Service health check',
        tags: ['Health'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'All services are healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'healthy'),
                        new OA\Property(
                            property: 'services',
                            properties: [
                                new OA\Property(
                                    property: 'postgresql',
                                    properties: [
                                        new OA\Property(property: 'ok', type: 'boolean', example: true),
                                        new OA\Property(property: 'latency_ms', type: 'number', format: 'float', example: 1.23),
                                    ],
                                    type: 'object',
                                ),
                                new OA\Property(
                                    property: 'redis',
                                    properties: [
                                        new OA\Property(property: 'ok', type: 'boolean', example: true),
                                        new OA\Property(property: 'latency_ms', type: 'number', format: 'float', example: 0.45),
                                    ],
                                    type: 'object',
                                ),
                                new OA\Property(
                                    property: 'rabbitmq',
                                    properties: [
                                        new OA\Property(property: 'ok', type: 'boolean', example: true),
                                        new OA\Property(property: 'latency_ms', type: 'number', format: 'float', example: 2.10),
                                    ],
                                    type: 'object',
                                ),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 503,
                description: 'One or more services are down',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'degraded'),
                        new OA\Property(
                            property: 'services',
                            properties: [
                                new OA\Property(
                                    property: 'postgresql',
                                    properties: [
                                        new OA\Property(property: 'ok', type: 'boolean', example: true),
                                        new OA\Property(property: 'latency_ms', type: 'number', format: 'float', example: 1.23),
                                    ],
                                    type: 'object',
                                ),
                                new OA\Property(
                                    property: 'redis',
                                    properties: [
                                        new OA\Property(property: 'ok', type: 'boolean', example: false),
                                        new OA\Property(property: 'error', type: 'string', example: 'Connection refused'),
                                    ],
                                    type: 'object',
                                ),
                                new OA\Property(
                                    property: 'rabbitmq',
                                    properties: [
                                        new OA\Property(property: 'ok', type: 'boolean', example: true),
                                        new OA\Property(property: 'latency_ms', type: 'number', format: 'float', example: 2.10),
                                    ],
                                    type: 'object',
                                ),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(Connection $redis): JsonResponse
    {
        $services = [
            'postgresql' => $this->checkPostgresql(),
            'redis' => $this->checkRedis($redis),
            'rabbitmq' => $this->checkRabbitmq(),
        ];

        $healthy = ! in_array(false, array_column($services, 'ok'), true);

        return response()->json(
            data: [
                'status' => $healthy ? 'healthy' : 'degraded',
                'services' => $services,
            ],
            status: $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    /** @return array{ok: bool, latency_ms?: float, error?: string} */
    private function checkPostgresql(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['ok' => true, 'latency_ms' => $latency];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, latency_ms?: float, error?: string} */
    private function checkRedis(Connection $redis): array
    {
        try {
            $start = microtime(true);
            $redis->command('ping');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['ok' => true, 'latency_ms' => $latency];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, latency_ms?: float, error?: string} */
    private function checkRabbitmq(): array
    {
        try {
            $config = config('queue.connections.rabbitmq');

            $amqpConfig = new AMQPConnectionConfig();
            $amqpConfig->setHost($config['host']);
            $amqpConfig->setPort((int) $config['port']);
            $amqpConfig->setUser($config['user']);
            $amqpConfig->setPassword($config['password']);
            $amqpConfig->setVhost($config['vhost']);
            $amqpConfig->setConnectionTimeout(3);
            $amqpConfig->setReadTimeout(3);
            $amqpConfig->setWriteTimeout(3);

            $start = microtime(true);
            $connection = AMQPConnectionFactory::create($amqpConfig);
            $connection->channel();
            $latency = round((microtime(true) - $start) * 1000, 2);
            $connection->close();

            return ['ok' => true, 'latency_ms' => $latency];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
