<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Gateways\GatewayResult;
use App\Models\Notification;
use App\Services\Webhook\ProviderSignatureService;
use Illuminate\Support\Str;
use Tests\IntegrationTestCase;

class EndToEndDeliveryTest extends IntegrationTestCase
{
    private const PROVIDER_MESSAGE_ID = 'TWILIO-ext-777';

    public function test_message_flows_from_queue_to_sent(): void
    {
        $this->fakeGateway(GatewayResult::accepted(self::PROVIDER_MESSAGE_ID));

        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Your code is 0451',
            'recipients' => ['555'],
            'is_transactional' => true,
        ], ['Request-Id' => (string) Str::uuid()])->assertStatus(202);

        $notification = Notification::query()->firstOrFail();

        $this->assertSame(NotificationStatus::Queued, $notification->status);

        $this->work($this->queueFor(NotificationChannel::Sms, true), 1);
        $notification->refresh();

        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertSame(self::PROVIDER_MESSAGE_ID, $notification->provider_message_id);
    }

    public function test_message_flows_from_queue_to_delivered_via_dlr(): void
    {
        $this->fakeGateway(GatewayResult::accepted(self::PROVIDER_MESSAGE_ID));

        $this->postJson('/api/v1/notifications/bulk', [
            'channel' => 'sms',
            'message' => 'Your code is 0451',
            'recipients' => ['555'],
            'is_transactional' => true,
        ], ['Request-Id' => (string) Str::uuid()])->assertStatus(202);

        $notification = Notification::query()->firstOrFail();
        $this->work($this->queueFor(NotificationChannel::Sms, true), 1);

        $this->assertSame(NotificationStatus::Sent, $notification->refresh()->status);

        $payload = ['status' => 'delivered', 'provider_message_id' => self::PROVIDER_MESSAGE_ID];

        $this->json(
            'POST',
            "/api/v1/notifications/{$notification->id}/delivery-receipt",
            $payload,
            ['X-Provider-Signature' => app(ProviderSignatureService::class)->sign(json_encode($payload))],
        )->assertOk()->assertJsonPath('status', 'delivered');

        $this->assertSame(NotificationStatus::Delivered, $notification->refresh()->status);
    }
}
