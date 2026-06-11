<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Services\Webhook\ProviderSignatureService;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\IntegrationTestCase;

class DeliveryReceiptWebhookTest extends IntegrationTestCase
{
    public function test_signed_receipt_marks_sent_notification_delivered(): void
    {
        $notification = Notification::factory()->status(NotificationStatus::Sent)->create();

        $this->postReceipt($notification, ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('status', 'delivered');
        $this->assertSame(NotificationStatus::Delivered, $notification->refresh()->status);
    }

    public function test_signed_receipt_can_mark_dropped(): void
    {
        $notification = Notification::factory()->status(NotificationStatus::Sent)->create();

        $this->postReceipt($notification, [
            'status' => 'dropped',
            'failure_reason' => 'handset unreachable',
        ])->assertOk();

        $notification->refresh();

        $this->assertSame(NotificationStatus::Dropped, $notification->status);
        $this->assertSame('handset unreachable', $notification->failure_reason);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $notification = Notification::factory()->status(NotificationStatus::Sent)->create();

        $this->postReceipt($notification, ['status' => 'delivered'], signature: 'forged')
            ->assertForbidden();
        $this->assertSame(NotificationStatus::Sent, $notification->refresh()->status);
    }

    public function test_receipt_for_already_finalized_is_idempotent_noop(): void
    {
        $notification = Notification::factory()->status(NotificationStatus::Delivered)->create();

        $this->postReceipt($notification, ['status' => 'dropped'])
            ->assertOk()
            ->assertJsonPath('status', 'delivered');
        $this->assertSame(NotificationStatus::Delivered, $notification->refresh()->status);
    }

    public function test_receipt_before_sent_is_conflict(): void
    {
        $notification = Notification::factory()->status(NotificationStatus::Queued)->create();

        $this->postReceipt($notification, ['status' => 'delivered'])
            ->assertStatus(409);
        $this->assertSame(NotificationStatus::Queued, $notification->refresh()->status);
    }

    public function test_receipt_for_unknown_notification_is_not_found(): void
    {
        $payload = ['status' => 'delivered'];

        $this->json(
            'POST',
            '/api/v1/notifications/999999/delivery-receipt',
            $payload,
            ['X-Provider-Signature' => app(ProviderSignatureService::class)->sign(json_encode($payload))],
        )->assertNotFound();
    }

    public function test_receipt_with_invalid_status_is_rejected(): void
    {
        $notification = Notification::factory()->status(NotificationStatus::Sent)->create();

        $this->postReceipt($notification, ['status' => 'queued'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
        $this->assertSame(NotificationStatus::Sent, $notification->refresh()->status);
    }

    public function test_missing_webhook_secret_makes_the_endpoint_fail_closed(): void
    {
        config(['notifications.webhook.secret' => '']);
        app()->forgetInstance(ProviderSignatureService::class);
        $this->withoutExceptionHandling();
        $notification = Notification::factory()->status(NotificationStatus::Sent)->create();
        $thrown = false;

        try {
            $this->postJson(
                "/api/v1/notifications/{$notification->id}/delivery-receipt",
                ['status' => 'delivered'],
                ['X-Provider-Signature' => 'irrelevant'],
            );
        } catch (RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('PROVIDER_WEBHOOK_SECRET', $e->getMessage());
        }

        $this->assertTrue($thrown, 'The webhook must fail closed when the signing secret is missing.');
        $this->assertSame(NotificationStatus::Sent, $notification->refresh()->status);
    }

    private function postReceipt(Notification $notification, array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload);
        $signature ??= app(ProviderSignatureService::class)->sign($body);

        return $this->json(
            'POST',
            "/api/v1/notifications/{$notification->id}/delivery-receipt",
            $payload,
            ['X-Provider-Signature' => $signature],
        );
    }
}
