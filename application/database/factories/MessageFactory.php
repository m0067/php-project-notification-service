<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'channel' => $this->faker->randomElement(NotificationChannel::cases()),
            'message_text' => $this->faker->sentence(),
            'is_transactional' => $this->faker->boolean(30),
        ];
    }

    public function transactional(): static
    {
        return $this->state(fn () => ['is_transactional' => true]);
    }
}
