<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Contracts\Services\IdempotencyServiceInterface;
use App\DTOs\SendNotificationDTO;
use App\Events\NotificationCreated;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

final class ProcessBulkNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly SendNotificationDTO $dto,
    ) {}

    public function handle(
        NotificationRepositoryInterface $repository,
        IdempotencyServiceInterface $idempotency,
    ): void {
        $dto = $this->dto;
        $created = [];

        DB::transaction(function () use ($dto, $repository, $idempotency, &$created) {
            foreach ($dto->recipientIds as $recipientId) {
                $perRecipientKey = $dto->recipientIdempotencyKey($recipientId);

                if (! $idempotency->acquireLock($perRecipientKey)) {
                    continue;
                }

                $notification = Notification::createNew(
                    recipientId: $recipientId,
                    channel: $dto->channel,
                    message: $dto->message,
                    priority: $dto->priority,
                    idempotencyKey: $perRecipientKey,
                );

                try {
                    $repository->save($notification);
                } catch (UniqueConstraintViolationException) {
                    continue;
                }

                $created[] = $notification;
            }
        });

        $queueName = $dto->priority->queueName($dto->channel);

        foreach ($created as $notification) {
            NotificationCreated::dispatch($notification);

            SendNotificationJob::dispatch($notification)
                ->onQueue($queueName);
        }
    }
}
