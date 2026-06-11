<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationStatus;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'subscriber_id' => (string) $this->faker->numberBetween(1000, 9999),
            'status' => NotificationStatus::Queued,
            'provider_message_id' => null,
            'failure_reason' => null,
        ];
    }

    public function status(NotificationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
