<?php

declare(strict_types=1);

namespace App\Gateways;

use Illuminate\Support\Facades\Cache;

/**
 * TODO: within the scope of the test task. A real Twilio integration is stubbed out.
 */
final class TwilioGateway implements Gateway
{
    private const ID_PREFIX = 'TWILIO-';

    private const ACCEPTED_THRESHOLD = 95;

    private const TRANSIENT_THRESHOLD = 96;

    private const MIN_LATENCY_US = 10_000;

    private const MAX_LATENCY_US = 50_000;

    private const MIN_RANDOM_NUMBER = 1;

    private const MAX_RANDOM_NUMBER = 100;

    private const IDEM_CACHE_PREFIX = 'gw:twilio:idem:';

    private const IDEM_TTL_SECONDS = 3_600;

    public function send(NotificationDto $dto): GatewayResult
    {
        $cacheKey = self::IDEM_CACHE_PREFIX.$dto->idempotencyKey;

        if (is_string($priorMessageId = Cache::get($cacheKey))) {
            return GatewayResult::accepted($priorMessageId);
        }

        usleep(random_int(self::MIN_LATENCY_US, self::MAX_LATENCY_US));

        $roll = random_int(self::MIN_RANDOM_NUMBER, self::MAX_RANDOM_NUMBER);

        if ($roll <= self::ACCEPTED_THRESHOLD) {
            $messageId = self::ID_PREFIX.$dto->id;
            Cache::put($cacheKey, $messageId, self::IDEM_TTL_SECONDS);

            return GatewayResult::accepted($messageId);
        }

        if ($roll <= self::TRANSIENT_THRESHOLD) {
            return GatewayResult::transient('temporary gateway error');
        }

        return GatewayResult::permanent('incorrect phone number');
    }
}
