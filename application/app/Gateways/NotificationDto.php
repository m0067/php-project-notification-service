<?php

declare(strict_types=1);

namespace App\Gateways;

use App\Models\Notification;

final readonly class NotificationDto
{
    public function __construct(
        public int $id,
        public string $recipient,
        public string $message,
        public string $idempotencyKey,
    ) {}

    public static function fromModel(Notification $notification): self
    {
        return new self(
            id: $notification->id,
            recipient: $notification->subscriber_id,
            message: $notification->message->message_text,
            idempotencyKey: $notification->external_idempotency_key,
        );
    }
}
