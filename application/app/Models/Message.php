<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannel;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $request_id
 * @property NotificationChannel $channel
 * @property string $message_text
 * @property bool $is_transactional
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'request_id',
        'channel',
        'message_text',
        'is_transactional',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'is_transactional' => 'boolean',
        ];
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
