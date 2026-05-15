<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit log entry for notification status transitions.
 *
 * @property-read int $id
 * @property-read string $notification_id
 * @property-read NotificationStatus $status
 * @property-read NotificationStatus|null $previous_status
 * @property-read array|null $context
 * @property-read Carbon|null $created_at
 */
class NotificationLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => NotificationStatus::class,
            'previous_status' => NotificationStatus::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function createEntry(
        Notification $notification,
        NotificationStatus $status,
        ?NotificationStatus $previousStatus = null,
        ?array $context = null,
    ): self {
        $log = new self();
        $log->notification_id = $notification->id;
        $log->status = $status;
        $log->previous_status = $previousStatus;
        $log->context = $context;
        $log->save();

        return $log;
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
