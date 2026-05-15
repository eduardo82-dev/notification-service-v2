<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\DTOs\NotificationFilterDTO;
use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function save(Notification $notification): void
    {
        $notification->save();
    }

    public function findById(string $id): ?Notification
    {
        return Notification::query()->find($id);
    }

    public function findByIdempotencyKey(string $key): ?Notification
    {
        return Notification::query()->where('idempotency_key', $key)->first();
    }

    public function paginateForSubscriber(NotificationFilterDTO $filter): LengthAwarePaginator
    {
        $query = Notification::query()->where('recipient_id', $filter->subscriberId);

        if ($filter->channel !== null) {
            $query->where('channel', $filter->channel->value);
        }

        if ($filter->status !== null) {
            $query->where('status', $filter->status->value);
        }

        return $query->orderByDesc('created_at')->paginate($filter->perPage);
    }
}
