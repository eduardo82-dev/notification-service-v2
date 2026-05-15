<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTOs\NotificationFilterDTO;
use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    public function save(Notification $notification): void;

    public function findById(string $id): ?Notification;

    public function findByIdempotencyKey(string $key): ?Notification;

    public function paginateForSubscriber(NotificationFilterDTO $filter): LengthAwarePaginator;
}
