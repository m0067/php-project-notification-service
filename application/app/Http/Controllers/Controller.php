<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Notifications API',
    description: 'Bulk notification service: accepts bulk dispatches (sms/email)',
)]
#[OA\Server(
    url: '/api/v1',
    description: 'API base prefix',
)]
#[OA\Tag(name: 'Notifications', description: 'Notification dispatch and history')]
#[OA\Tag(name: 'Webhooks', description: 'Provider callbacks')]
abstract class Controller {}
