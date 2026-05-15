<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\NotificationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListSubscriberNotificationsRequest;
use App\Http\Resources\NotificationCollection;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'NotificationResource',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c'),
        new OA\Property(property: 'channel', type: 'string', example: 'sms', enum: ['sms', 'email']),
        new OA\Property(property: 'recipient_id', type: 'string', example: 'sub_001'),
        new OA\Property(property: 'message', type: 'string', example: 'Your access code is 4829'),
        new OA\Property(property: 'priority', type: 'string', example: 'transactional', enum: ['transactional', 'marketing']),
        new OA\Property(property: 'status', type: 'string', example: 'queued', enum: ['queued', 'sent', 'delivered', 'rejected']),
        new OA\Property(property: 'attempts', type: 'integer', example: 0),
        new OA\Property(property: 'delivered_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'rejected_reason', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
final class SubscriberNotificationController extends Controller
{
    public function __construct(
        private readonly NotificationServiceInterface $service,
    ) {}

    #[OA\Get(
        path: '/api/v1/subscribers/{subscriberId}/notifications',
        description: 'Returns paginated notification history for a specific subscriber, with optional filtering by status and channel.',
        summary: 'List subscriber notifications',
        tags: ['Subscribers'],
        parameters: [
            new OA\Parameter(name: 'subscriberId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'sub_001'),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['queued', 'sent', 'delivered', 'rejected'])),
            new OA\Parameter(name: 'channel', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['sms', 'email'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of notifications',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/NotificationResource'),
                        ),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ],
                ),
            ),
        ],
    )]
    public function index(ListSubscriberNotificationsRequest $request): NotificationCollection
    {
        $dto = $request->toDTO();
        $paginated = $this->service->getSubscriberNotifications($dto);

        return new NotificationCollection($paginated);
    }
}
