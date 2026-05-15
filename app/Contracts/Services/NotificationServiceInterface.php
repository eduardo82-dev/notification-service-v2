<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\NotificationFilterDTO;
use App\DTOs\SendNotificationDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationServiceInterface
{
    public function sendBulk(SendNotificationDTO $dto): void;

    public function getSubscriberNotifications(NotificationFilterDTO $filter): LengthAwarePaginator;
}
