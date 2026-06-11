<?php

declare(strict_types=1);

namespace App\Gateways;

use App\Enums\NotificationChannel;
use Illuminate\Support\Manager;

final class GatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('notifications.gateway.default', NotificationChannel::Sms->value);
    }

    protected function createSmsDriver(): Gateway
    {
        return $this->container->make(TwilioGateway::class);
    }

    protected function createEmailDriver(): Gateway
    {
        return $this->container->make(GmailGateway::class);
    }
}
