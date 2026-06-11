<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Gateways\GatewayOutcome;
use App\Gateways\GmailGateway;
use App\Gateways\NotificationDto;
use App\Gateways\TwilioGateway;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GatewayIdempotencyTest extends TestCase
{
    /** @return array<string, array{class-string}> */
    public static function gateways(): array
    {
        return [
            'twilio' => [TwilioGateway::class],
            'gmail' => [GmailGateway::class],
        ];
    }

    #[DataProvider('gateways')]
    public function test_same_idempotency_key_replays_the_accepted_result(string $gatewayClass): void
    {
        $gateway = new $gatewayClass;
        $key = (string) Str::uuid();
        $dto = $this->dto(12345, $key);

        do {
            $first = $gateway->send($dto);
        } while ($first->outcome !== GatewayOutcome::Accepted);

        $second = $gateway->send($dto);

        $this->assertSame(GatewayOutcome::Accepted, $second->outcome);
        $this->assertSame($first->providerMessageId, $second->providerMessageId);
    }

    public function test_different_keys_are_independent(): void
    {
        $gateway = new TwilioGateway;

        do {
            $resultA = $gateway->send($this->dto(1, (string) Str::uuid()));
        } while ($resultA->outcome !== GatewayOutcome::Accepted);

        $resultB = $gateway->send($this->dto(2, (string) Str::uuid()));

        if ($resultB->outcome === GatewayOutcome::Accepted) {
            $this->assertNotSame($resultA->providerMessageId, $resultB->providerMessageId);
        } else {
            $this->assertContains($resultB->outcome, [GatewayOutcome::Transient, GatewayOutcome::Permanent]);
        }
    }

    private function dto(int $id, string $key): NotificationDto
    {
        return new NotificationDto(
            id: $id,
            recipient: '999',
            message: 'Identical message',
            idempotencyKey: $key,
        );
    }
}
