<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use Illuminate\Support\Str;
use Tests\IntegrationTestCase;

class BulkNotificationApiTest extends IntegrationTestCase
{
    public function test_it_validates_required_fields(): void
    {
        $this->postJson('/api/v1/notifications/bulk', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['request_id', 'channel', 'message', 'recipients']);
    }

    public function test_it_requires_request_id_to_be_a_uuid(): void
    {
        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Hi',
            'recipients' => ['1'],
        ], ['Request-Id' => 'not-a-uuid'])->assertJsonValidationErrors(['request_id']);
    }

    public function test_it_rejects_unknown_channel(): void
    {
        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'telegram',
            'message' => 'Hi',
            'recipients' => ['1'],
        ])->assertJsonValidationErrors(['channel']);
    }

    public function test_it_rejects_empty_recipients(): void
    {
        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Hi',
            'recipients' => [],
        ], ['Request-Id' => (string) Str::uuid()])->assertJsonValidationErrors(['recipients']);
    }

    public function test_it_rejects_non_string_recipients(): void
    {
        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Hi',
            'recipients' => [100, 200],
        ], ['Request-Id' => (string) Str::uuid()])->assertJsonValidationErrors(['recipients.0', 'recipients.1']);
    }

    public function test_it_rejects_too_long_message(): void
    {
        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => str_repeat('a', 1001),
            'recipients' => ['1'],
        ], ['Request-Id' => (string) Str::uuid()])->assertJsonValidationErrors(['message']);
    }

    public function test_transactional_bulk_is_accepted_and_routed_to_high_queue(): void
    {
        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Access code 0451',
            'recipients' => ['100', '200'],
            'is_transactional' => true,
        ], ['Request-Id' => (string) Str::uuid()])
            ->assertStatus(202)
            ->assertJsonPath('accepted', 2);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'subscriber_id' => '100',
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('messages', [
            'channel' => 'sms',
            'is_transactional' => true,
        ]);

        $this->assertSame(2, $this->queueSize($this->queueFor(NotificationChannel::Sms, true)));
        $this->assertSame(0, $this->queueSize($this->queueFor(NotificationChannel::Sms, false)));
    }

    public function test_marketing_bulk_is_routed_to_low_queue(): void
    {
        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'email',
            'message' => 'Summer sale',
            'recipients' => ['300'],
        ], ['Request-Id' => (string) Str::uuid()])->assertStatus(202);

        $this->assertSame(1, $this->queueSize($this->queueFor(NotificationChannel::Email, false)));
        $this->assertSame(0, $this->queueSize($this->queueFor(NotificationChannel::Email, true)));
    }
}
