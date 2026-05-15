<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Gateways\GatewayResolverInterface;
use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Events\NotificationDelivered;
use App\Events\NotificationRejected;
use App\Events\NotificationSent;
use App\Exceptions\GatewayFailureException;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly Notification $notification,
    ) {}

    public function handle(
        GatewayResolverInterface $gatewayResolver,
        NotificationRepositoryInterface $repository,
    ): void {
        $notification = $this->notification;

        if ($notification->status->isFinal()) {
            return;
        }

        $notification->incrementAttempt();
        $repository->save($notification);

        try {
            $gateway = $gatewayResolver->resolve($notification->channel);
            $gateway->send($notification->recipient_id, $notification->message);

            $notification->markAsSent();
            $repository->save($notification);
            NotificationSent::dispatch($notification);

            // Заглушка: немедленно пометить как доставленную (в реальной системе будет использоваться асинхронный перехватчик)
            $notification->markAsDelivered();
            $repository->save($notification);
            NotificationDelivered::dispatch($notification);
        } catch (GatewayFailureException $e) {
            if ($this->attempts() >= $this->tries) {
                $notification->markAsRejected($e->getMessage());
                $repository->save($notification);
                NotificationRejected::dispatch($notification);

                return;
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $notification = $this->notification;

        if (! $notification->status->isFinal()) {
            $notification->markAsRejected($exception->getMessage());
            app(NotificationRepositoryInterface::class)->save($notification);
            NotificationRejected::dispatch($notification);
        }
    }
}
