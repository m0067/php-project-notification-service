<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\NotificationStatus;
use App\Http\Requests\DeliveryReceiptRequest;
use App\Models\Notification;

final readonly class DeliveryReceiptDto
{
    public function __construct(
        public Notification $notification,
        public NotificationStatus $status,
        public ?string $failureReason,
    ) {}

    public static function fromRequest(DeliveryReceiptRequest $request, Notification $notification): self
    {
        return new self(
            notification: $notification,
            status: NotificationStatus::from($request->string('status')->value()),
            failureReason: $request->input('failure_reason'),
        );
    }
}
