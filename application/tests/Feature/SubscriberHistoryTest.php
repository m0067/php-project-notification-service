<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notification;
use Tests\IntegrationTestCase;

class SubscriberHistoryTest extends IntegrationTestCase
{
    public function test_returns_only_the_subscribers_notifications(): void
    {
        Notification::factory()->count(2)->create(['subscriber_id' => '42']);
        Notification::factory()->create(['subscriber_id' => 'other']);

        $this->getJson('/api/v1/notifications/subscriber/42')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.subscriber_id', '42');
    }

    public function test_returns_empty_for_unknown_subscriber(): void
    {
        $this->getJson('/api/v1/notifications/subscriber/nobody')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_history_is_cursor_paginated(): void
    {
        Notification::factory()->count(20)->create(['subscriber_id' => '77']);

        $first = $this->getJson('/api/v1/notifications/subscriber/77?per_page=15')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15);

        $cursor = $first->json('meta.next_cursor');

        $this->assertNotNull($cursor);
        $this->getJson("/api/v1/notifications/subscriber/77?per_page=15&cursor={$cursor}")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.next_cursor', null);
    }

    public function test_history_rejects_per_page_above_the_max(): void
    {
        $this->getJson('/api/v1/notifications/subscriber/nobody?per_page=9999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_history_defaults_per_page_when_omitted(): void
    {
        Notification::factory()->count(1)->create(['subscriber_id' => '88']);

        $this->getJson('/api/v1/notifications/subscriber/88')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25);
    }
}
