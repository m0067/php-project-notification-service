<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['1001', '1002', '1003'] as $subscriberId) {
            foreach (NotificationStatus::cases() as $status) {
                Notification::factory()
                    ->status($status)
                    ->create(['subscriber_id' => $subscriberId]);
            }
        }
    }
}
