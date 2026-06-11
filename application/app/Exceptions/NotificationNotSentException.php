<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\NotificationStatus;
use RuntimeException;

final class NotificationNotSentException extends RuntimeException
{
    public function __construct(
        public readonly NotificationStatus $status,
    ) {
        parent::__construct('notification is not in "sent" state');
    }
}
