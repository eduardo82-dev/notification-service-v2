<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Contracts\Services\IdempotencyServiceInterface;
use App\Contracts\Services\NotificationServiceInterface;
use App\DTOs\NotificationFilterDTO;
use App\DTOs\SendNotificationDTO;
use App\Exceptions\DuplicateNotificationException;
use App\Jobs\ProcessBulkNotificationJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class NotificationService implements NotificationServiceInterface
{
    public function __construct(
        private NotificationRepositoryInterface $repository,
        private IdempotencyServiceInterface $idempotency,
    ) {}

    public function sendBulk(SendNotificationDTO $dto): void
    {
        if (! $this->idempotency->acquireLock($dto->idempotencyKey)) {
            throw new DuplicateNotificationException($dto->idempotencyKey);
        }

        $chunkSize = (int) config('notifications.chunk_size', 500);

        foreach (array_chunk($dto->recipientIds, $chunkSize) as $chunk) {
            $chunkDto = new SendNotificationDTO(
                channel: $dto->channel,
                message: $dto->message,
                recipientIds: $chunk,
                priority: $dto->priority,
                idempotencyKey: $dto->idempotencyKey,
            );

            ProcessBulkNotificationJob::dispatch($chunkDto)
                ->onQueue($dto->priority->queueName($dto->channel));
        }
    }

    public function getSubscriberNotifications(NotificationFilterDTO $filter): LengthAwarePaginator
    {
        return $this->repository->paginateForSubscriber($filter);
    }
}
