<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\NotificationChannel;
use App\Gateways\Gateway;
use App\Gateways\GatewayResult;
use App\Gateways\TwilioGateway;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Throwable;

abstract class IntegrationTestCase extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->purgeQueues();
    }

    protected function fakeGateway(GatewayResult $result, string $gatewayClass = TwilioGateway::class): void
    {
        $gateway = $this->createMock(Gateway::class);
        $gateway->method('send')->willReturn($result);
        $this->app->instance($gatewayClass, $gateway);
    }

    protected function queueFor(NotificationChannel $channel, bool $transactional): string
    {
        return $channel->queue($transactional);
    }

    protected function allQueues(): array
    {
        $queues = [];

        foreach (NotificationChannel::cases() as $channel) {
            $queues[] = $channel->queue(true);
            $queues[] = $channel->queue(false);
        }

        return $queues;
    }

    protected function tearDown(): void
    {
        $this->purgeQueues();

        parent::tearDown();
    }

    protected function purgeQueues(): void
    {
        foreach ($this->allQueues() as $queue) {
            try {
                Queue::connection('rabbitmq')->purge($queue);
            } catch (Throwable) {
            }
        }
    }

    protected function work(string $queues, int $maxJobs = 10): void
    {
        Artisan::call('queue:work', [
            'connection' => 'rabbitmq',
            '--queue' => $queues,
            '--max-jobs' => $maxJobs,
            '--stop-when-empty' => true,
            '--tries' => 5,
        ]);
    }

    protected function queueSize(string $queue): int
    {
        return Queue::connection('rabbitmq')->size($queue);
    }
}
