<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\BulkNotificationDto;
use App\Exceptions\RequestAlreadyProcessingException;
use App\Http\Resources\NotificationCollection;
use App\Models\Notification;

final class NotificationService
{
    public function __construct(
        private readonly NotificationDispatcherService $dispatcher,
        private readonly IdempotencyService $idempotency,
    ) {}

    /**
     * @return array{accepted: int, notification_ids: array<int, int>}
     */
    public function processBulk(BulkNotificationDto $dto): array
    {
        if ($cached = $this->idempotency->find($dto->requestId)) {
            return $cached;
        }

        $lock = $this->idempotency->lock($dto->requestId);

        if (! $lock->get()) {
            throw new RequestAlreadyProcessingException;
        }

        try {
            if ($cached = $this->idempotency->find($dto->requestId)) {
                return $cached;
            }

            $ids = $this->dispatcher->dispatchBulk($dto);

            $response = [
                'accepted' => count($ids),
                'notification_ids' => $ids,
            ];

            $this->idempotency->remember($dto->requestId, $response);

            return $response;
        } finally {
            $lock->release();
        }
    }

    public function history(string $subscriberId, int $perPage, ?string $cursor): NotificationCollection
    {
        $notifications = Notification::query()
            ->with('message')
            ->where('subscriber_id', $subscriberId)
            ->orderByDesc('id')
            ->cursorPaginate(perPage: $perPage, cursor: $cursor)
            ->withQueryString();

        return new NotificationCollection($notifications);
    }
}
