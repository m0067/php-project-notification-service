<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationStatus;
use App\Models\Notification;

final class NotificationStateMachineService
{
    private const TRANSITIONS = [
        NotificationStatus::Queued->value => [NotificationStatus::Sent, NotificationStatus::Dropped],
        NotificationStatus::Sent->value => [NotificationStatus::Delivered, NotificationStatus::Dropped],
    ];

    public function markSent(Notification $notification, ?string $providerMessageId = null): bool
    {
        if (! $this->canTransition($notification->status, NotificationStatus::Sent)) {
            return false;
        }

        $notification->provider_message_id = $providerMessageId;

        return $notification->transitionTo(NotificationStatus::Sent);
    }

    public function markDelivered(Notification $notification): bool
    {
        if (! $this->canTransition($notification->status, NotificationStatus::Delivered)) {
            return false;
        }

        return $notification->transitionTo(NotificationStatus::Delivered);
    }

    public function markDropped(Notification $notification, ?string $reason = null): bool
    {
        if (! $this->canTransition($notification->status, NotificationStatus::Dropped)) {
            return false;
        }

        return $notification->transitionTo(NotificationStatus::Dropped, $reason);
    }

    private function canTransition(NotificationStatus $from, NotificationStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }
}
