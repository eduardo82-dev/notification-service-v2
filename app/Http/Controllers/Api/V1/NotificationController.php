<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\NotificationServiceInterface;
use App\Exceptions\DuplicateNotificationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendNotificationRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Info(
    version: '1.0.0',
    description: 'API for sending mass notifications and tracking delivery statuses',
    title: 'Notification Service API',
)]
final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationServiceInterface $service,
    ) {}

    #[OA\Post(
        path: '/api/v1/notifications/send',
        description: 'Accepts a batch of recipient IDs and dispatches notifications to the queue. Returns 202 Accepted with created notification records.',
        summary: 'Send mass notifications',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['channel', 'message', 'recipient_ids', 'priority', 'idempotency_key'],
                properties: [
                    new OA\Property(property: 'channel', type: 'string', example: 'sms', enum: ['sms', 'email']),
                    new OA\Property(property: 'message', type: 'string', example: 'Your access code is 4829'),
                    new OA\Property(
                        property: 'recipient_ids',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['sub_001', 'sub_002', 'sub_003'],
                    ),
                    new OA\Property(property: 'priority', type: 'string', example: 'transactional', enum: ['transactional', 'marketing']),
                    new OA\Property(property: 'idempotency_key', type: 'string', example: 'batch-2026-05-15-abc123'),
                ],
            ),
        ),
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Batch accepted for processing',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'idempotency_key', type: 'string', example: 'batch-2026-05-15-abc123'),
                        new OA\Property(property: 'total_recipients', type: 'integer', example: 3),
                        new OA\Property(property: 'priority', type: 'string', example: 'transactional'),
                        new OA\Property(property: 'channel', type: 'string', example: 'sms'),
                    ],
                ),
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function send(SendNotificationRequest $request): JsonResponse
    {
        $dto = $request->toDTO();

        try {
            $this->service->sendBulk($dto);
        } catch (DuplicateNotificationException) {
            return response()->json([
                'message' => 'Duplicate request',
                'idempotency_key' => $dto->idempotencyKey,
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'idempotency_key' => $dto->idempotencyKey,
            'total_recipients' => count($dto->recipientIds),
            'priority' => $dto->priority->value,
            'channel' => $dto->channel->value,
        ], Response::HTTP_ACCEPTED);
    }
}
