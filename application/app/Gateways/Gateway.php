<?php

declare(strict_types=1);

namespace App\Gateways;

interface Gateway
{
    public function send(NotificationDto $dto): GatewayResult;
}
