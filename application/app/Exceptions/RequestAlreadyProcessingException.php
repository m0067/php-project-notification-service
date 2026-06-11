<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class RequestAlreadyProcessingException extends RuntimeException
{
    public function __construct(
        string $message = 'This Request-Id is already being processed.',
    ) {
        parent::__construct($message);
    }
}
