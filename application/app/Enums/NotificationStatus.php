<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationStatus: string
{
    case Queued = 'queued';

    case Sent = 'sent';

    case Delivered = 'delivered';

    case Dropped = 'dropped';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Dropped], true);
    }
}
