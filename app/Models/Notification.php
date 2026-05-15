<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Notification aggregate root.
 *
 * No mass assignment — use the named constructor createNew().
 *
 * @property-read string $id
 * @property-read string $recipient_id
 * @property-read Channel $channel
 * @property-read string $message
 * @property-read Priority $priority
 * @property-read NotificationStatus $status
 * @property-read string $idempotency_key
 * @property-read int $attempts
 * @property-read Carbon|null $last_attempted_at
 * @property-read Carbon|null $delivered_at
 * @property-read string|null $rejected_reason
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, NotificationLog> $logs
 */
class Notification extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'priority' => Priority::class,
            'status' => NotificationStatus::class,
            'last_attempted_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public static function createNew(
        string $recipientId,
        Channel $channel,
        string $message,
        Priority $priority,
        string $idempotencyKey,
    ): self {
        $notification = new self();
        $notification->id = (string) Str::uuid();
        $notification->recipient_id = $recipientId;
        $notification->channel = $channel;
        $notification->message = $message;
        $notification->priority = $priority;
        $notification->status = NotificationStatus::QUEUED;
        $notification->idempotency_key = $idempotencyKey;
        $notification->attempts = 0;

        return $notification;
    }

    public function markAsSent(): void
    {
        $this->assertNotFinal();
        $this->status = NotificationStatus::SENT;
    }

    public function markAsDelivered(): void
    {
        if ($this->status === NotificationStatus::REJECTED) {
            throw new InvalidArgumentException(
                "Cannot mark rejected notification [{$this->id}] as delivered."
            );
        }

        $this->status = NotificationStatus::DELIVERED;
        $this->delivered_at = now();
    }

    public function markAsRejected(string $reason): void
    {
        if ($this->status === NotificationStatus::DELIVERED) {
            throw new InvalidArgumentException(
                "Cannot reject already delivered notification [{$this->id}]."
            );
        }

        $this->status = NotificationStatus::REJECTED;
        $this->rejected_reason = $reason;
    }

    public function incrementAttempt(): void
    {
        $this->attempts++;
        $this->last_attempted_at = now();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    private function assertNotFinal(): void
    {
        if ($this->status->isFinal()) {
            throw new InvalidArgumentException(
                "Notification [{$this->id}] is in final status [{$this->status->value}]."
            );
        }
    }
}
