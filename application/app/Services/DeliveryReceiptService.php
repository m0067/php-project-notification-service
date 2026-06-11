<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\DeliveryReceiptDto;
use App\Enums\NotificationStatus;
use App\Exceptions\NotificationNotSentException;

final class DeliveryReceiptService
{
    public function __construct(
        private readonly NotificationStateMachineService $states,
    ) {}

    /**
     * @return array{status: string, message?: string}
     */
    public function apply(DeliveryReceiptDto $dto): array
    {
        $notification = $dto->notification;

        if ($notification->status->isTerminal()) {
            return [
                'status' => $notification->status->value,
                'message' => 'already finalized',
            ];
        }

        if ($notification->status !== NotificationStatus::Sent) {
            throw new NotificationNotSentException($notification->status);
        }

        match ($dto->status) {
            NotificationStatus::Delivered => $this->states->markDelivered($notification),
            default => $this->states->markDropped($notification, $dto->failureReason),
        };

        return ['status' => $notification->status->value];
    }
}
