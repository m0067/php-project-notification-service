<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Exceptions\TransientGatewayException;
use App\Gateways\GatewayManager;
use App\Gateways\GatewayOutcome;
use App\Gateways\NotificationDto;
use App\Models\Notification;
use App\Services\NotificationStateMachineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $notificationId,
        public NotificationChannel $channel,
    ) {}

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function middleware(): array
    {
        return [new RateLimitedWithRedis("gateway:{$this->channel->value}")];
    }

    public function handle(GatewayManager $gateways, NotificationStateMachineService $states): void
    {
        $notification = Notification::with('message')->find($this->notificationId);

        if ($notification === null || $notification->status !== NotificationStatus::Queued) {
            return;
        }

        $result = $gateways->driver($this->channel->value)->send(NotificationDto::fromModel($notification));

        match ($result->outcome) {
            GatewayOutcome::Accepted => $states->markSent($notification, $result->providerMessageId),
            GatewayOutcome::Permanent => $states->markDropped($notification, $result->reason),
            GatewayOutcome::Transient => throw new TransientGatewayException(
                $result->reason ?? 'transient gateway error'
            ),
        };
    }

    public function failed(Throwable $e): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification !== null) {
            app(NotificationStateMachineService::class)
                ->markDropped($notification, 'retries exhausted: '.$e->getMessage());
        }
    }
}
