<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\NotificationChannel;
use App\Http\Requests\BulkNotificationRequest;

final readonly class BulkNotificationDto
{
    /**
     * @param  array<int, string>  $recipients
     */
    public function __construct(
        public string $requestId,
        public NotificationChannel $channel,
        public string $message,
        public array $recipients,
        public bool $isTransactional,
    ) {}

    public static function fromRequest(BulkNotificationRequest $request): self
    {
        return new self(
            requestId: $request->string('request_id')->value(),
            channel: NotificationChannel::from($request->string('channel')->value()),
            message: $request->string('message')->value(),
            recipients: $request->validated('recipients'),
            isTransactional: $request->boolean('is_transactional'),
        );
    }
}
