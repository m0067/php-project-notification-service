<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationStatus;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

/**
 * @property int $id
 * @property int $message_id
 * @property string $subscriber_id
 * @property NotificationStatus $status
 * @property string|null $provider_message_id
 * @property string|null $failure_reason
 * @property-read Message $message
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'message_id',
        'subscriber_id',
        'status',
        'provider_message_id',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => NotificationStatus::class,
        ];
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function transitionTo(NotificationStatus $status, ?string $failureReason = null): bool
    {
        if ($this->status->isTerminal()) {
            return false;
        }

        $this->status = $status;
        $this->failure_reason = $failureReason;
        $this->save();

        return true;
    }

    /**
     * * @property-read string $external_idempotency_key
     */
    protected function externalIdempotencyKey(): Attribute
    {
        return Attribute::get(
            fn () => Uuid::uuid5(
                $this->message->request_id,
                $this->subscriber_id
            )->toString()
        );
    }
}
