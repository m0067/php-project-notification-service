<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationChannel: string
{
    case Sms = 'sms';
    case Email = 'email';

    public function queue(bool $transactional): string
    {
        $priority = $transactional ? 'high' : 'low';

        return config("notifications.queues.{$this->value}.{$priority}");
    }
}
