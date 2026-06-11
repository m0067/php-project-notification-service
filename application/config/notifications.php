<?php

declare(strict_types=1);

use App\Enums\NotificationChannel;

return [
    'gateway' => [
        'default' => env('NOTIFICATIONS_GATEWAY_DEFAULT', NotificationChannel::Sms->value),
        'rate_limit' => [
            NotificationChannel::Sms->value => (int) env('SMS_RATE_LIMIT_PER_SECOND', 100),
            NotificationChannel::Email->value => (int) env('EMAIL_RATE_LIMIT_PER_SECOND', 200),
        ],
    ],

    'idempotency' => [
        'ttl' => (int) env('IDEMPOTENCY_TTL', 3600),
        'prefix' => 'notif:idem:',
    ],

    'webhook' => [
        'secret' => env('PROVIDER_WEBHOOK_SECRET'),
    ],

    'queues' => [
        NotificationChannel::Sms->value => [
            'high' => env('RABBITMQ_QUEUE_SMS_HIGH', 'notifications_sms_high'),
            'low' => env('RABBITMQ_QUEUE_SMS_LOW', 'notifications_sms_low'),
        ],
        NotificationChannel::Email->value => [
            'high' => env('RABBITMQ_QUEUE_EMAIL_HIGH', 'notifications_email_high'),
            'low' => env('RABBITMQ_QUEUE_EMAIL_LOW', 'notifications_email_low'),
        ],
    ],
];
