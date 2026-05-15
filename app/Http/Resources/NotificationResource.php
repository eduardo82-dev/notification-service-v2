<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
final class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'recipient_id' => $this->recipient_id,
            'message' => $this->message,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'rejected_reason' => $this->when(
                $this->status === NotificationStatus::REJECTED,
                $this->rejected_reason,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
