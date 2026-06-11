<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Exceptions\TransientGatewayException;
use App\Gateways\Gateway;
use App\Gateways\GatewayResult;
use App\Gateways\TwilioGateway;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use RuntimeException;
use Tests\IntegrationTestCase;

class SendNotificationJobTest extends IntegrationTestCase
{
    public function test_accepted_marks_sent_with_provider_message_id(): void
    {
        $this->fakeSmsGateway(GatewayResult::accepted('TWILIO-abc123'));
        $notification = $this->queuedNotification();
        $this->handle($notification);
        $notification->refresh();

        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertSame('TWILIO-abc123', $notification->provider_message_id);
    }

    public function test_permanent_error_marks_dropped_without_retry(): void
    {
        $this->fakeSmsGateway(GatewayResult::permanent('invalid phone number'));
        $notification = $this->queuedNotification();
        $this->handle($notification);
        $notification->refresh();

        $this->assertSame(NotificationStatus::Dropped, $notification->status);
        $this->assertStringContainsString('invalid phone number', (string) $notification->failure_reason);
    }

    public function test_transient_error_throws_to_trigger_retry(): void
    {
        $this->fakeSmsGateway(GatewayResult::transient('temporary provider error'));
        $notification = $this->queuedNotification();

        try {
            $this->handle($notification);
            $this->fail('TransientGatewayException was expected');
        } catch (TransientGatewayException) {
            // expected — the exception triggers a retry by backoff
        }

        $notification->refresh();

        $this->assertSame(NotificationStatus::Queued, $notification->status);
    }

    public function test_failed_hook_drops_after_retries_exhausted(): void
    {
        $notification = $this->queuedNotification();

        (new SendNotificationJob($notification->id, NotificationChannel::Sms))->failed(new RuntimeException('boom'));

        $notification->refresh();

        $this->assertSame(NotificationStatus::Dropped, $notification->status);
        $this->assertStringContainsString('retries exhausted', (string) $notification->failure_reason);
    }

    public function test_job_is_rate_limited_per_channel_via_redis(): void
    {
        $middleware = (new SendNotificationJob(1, NotificationChannel::Email))->middleware();

        $this->assertInstanceOf(RateLimitedWithRedis::class, $middleware[0]);
    }

    private function queuedNotification(string $recipient = '1'): Notification
    {
        return Notification::factory()->status(NotificationStatus::Queued)->create([
            'subscriber_id' => $recipient,
        ]);
    }

    private function fakeSmsGateway(GatewayResult $result): void
    {
        $gateway = $this->createMock(Gateway::class);
        $gateway->method('send')->willReturn($result);
        $this->app->instance(TwilioGateway::class, $gateway);
    }

    private function handle(Notification $notification): void
    {
        app()->call([new SendNotificationJob($notification->id, NotificationChannel::Sms), 'handle']);
    }
}
