<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Gateways\GatewayResult;
use App\Models\Notification;
use App\Services\IdempotencyService;
use Illuminate\Support\Str;
use Tests\IntegrationTestCase;

class IdempotencyTest extends IntegrationTestCase
{
    public function test_duplicate_request_id_is_accepted_once(): void
    {
        $this->fakeGateway(GatewayResult::accepted('TWILIO-ext-1'));
        $payload = [
            'channel' => 'sms',
            'message' => 'Identical message',
            'recipients' => ['999'],
            'is_transactional' => false,
        ];
        $headers = ['Request-Id' => (string) Str::uuid()];

        $first = $this->postJson('/api/v1/notifications/bulk', $payload, $headers)->assertStatus(202);
        $second = $this->postJson('/api/v1/notifications/bulk', $payload, $headers)->assertStatus(202);
        $this->assertSame($first->json('notification_ids'), $second->json('notification_ids'));
        $this->assertDatabaseCount('notifications', 1);

        $this->work($this->queueFor(NotificationChannel::Sms, false), 5);

        $this->assertSame(1, Notification::query()->where('status', 'sent')->count());
        $this->assertSame(0, Notification::query()->where('status', 'dropped')->count());
    }

    public function test_different_request_ids_are_processed_independently(): void
    {
        $this->fakeGateway(GatewayResult::accepted('TWILIO-ext-2'));
        $base = [
            'channel' => 'sms',
            'message' => 'Identical message',
            'recipients' => ['999'],
            'is_transactional' => false,
        ];

        $this->postJson('/api/v1/notifications/bulk', $base, ['Request-Id' => (string) Str::uuid()])->assertStatus(202);
        $this->postJson('/api/v1/notifications/bulk', $base, ['Request-Id' => (string) Str::uuid()])->assertStatus(202);
        $this->assertDatabaseCount('notifications', 2);

        $this->work($this->queueFor(NotificationChannel::Sms, false), 5);

        $this->assertSame(2, Notification::query()->where('status', 'sent')->count());
    }

    public function test_concurrent_duplicate_request_id_is_conflicted(): void
    {
        $uuid = (string) Str::uuid();

        $lock = app(IdempotencyService::class)->lock($uuid);

        $this->assertTrue($lock->get());

        try {
            $this->postJson('/api/v1/notifications/bulk', [
                'channel' => 'sms',
                'message' => 'concurrent',
                'recipients' => ['999'],
            ], ['Request-Id' => $uuid])->assertStatus(409);
        } finally {
            $lock->release();
        }

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_request_id_is_accepted_from_the_idempotency_header(): void
    {
        $this->fakeGateway(GatewayResult::accepted('TWILIO-ext-h'));

        $payload = [
            'channel' => 'sms',
            'message' => 'header-keyed',
            'recipients' => ['999'],
            'is_transactional' => false,
        ];
        $headers = ['Request-Id' => (string) Str::uuid()];

        $first = $this->postJson('/api/v1/notifications/bulk', $payload, $headers)->assertStatus(202);
        $second = $this->postJson('/api/v1/notifications/bulk', $payload, $headers)->assertStatus(202);

        $this->assertSame($first->json('notification_ids'), $second->json('notification_ids'));
        $this->assertDatabaseCount('notifications', 1);
    }
}
