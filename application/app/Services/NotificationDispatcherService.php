<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\BulkNotificationDto;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final class NotificationDispatcherService
{
    private const CHUNK_SIZE = 1000;

    /**
     * @return array<int, int> ids of the created notifications
     */
    public function dispatchBulk(BulkNotificationDto $dto): array
    {
        try {
            $message = Message::create([
                'request_id' => $dto->requestId,
                'channel' => $dto->channel,
                'message_text' => $dto->message,
                'is_transactional' => $dto->isTransactional,
            ]);
        } catch (UniqueConstraintViolationException) {
            return Message::where('request_id', $dto->requestId)
                ->firstOrFail()
                ->notifications()
                ->orderBy('id')
                ->pluck('id')
                ->all();
        }

        $queue = $dto->channel->queue($dto->isTransactional);
        $now = now();
        $ids = [];
        $cursor = 0;

        foreach (array_chunk($dto->recipients, self::CHUNK_SIZE) as $recipients) {
            $chunkIds = DB::transaction(static function () use ($message, $recipients, $now, $cursor) {
                Notification::insert(array_map(static fn (string $recipient): array => [
                    'message_id' => $message->id,
                    'subscriber_id' => $recipient,
                    'status' => NotificationStatus::Queued->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $recipients));

                return Notification::where('message_id', $message->id)
                    ->where('id', '>', $cursor)
                    ->orderBy('id')
                    ->pluck('id');
            });

            $cursor = $chunkIds->last() ?? $cursor;

            Queue::bulk(
                $chunkIds->map(static fn (int $id) => new SendNotificationJob($id, $dto->channel))->all(),
                '',
                $queue,
            );

            foreach ($chunkIds as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
