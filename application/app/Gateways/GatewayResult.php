<?php

declare(strict_types=1);

namespace App\Gateways;

final readonly class GatewayResult
{
    public function __construct(
        public GatewayOutcome $outcome,
        public ?string $providerMessageId = null,
        public ?string $reason = null,
    ) {}

    public static function accepted(string $providerMessageId): self
    {
        return new self(GatewayOutcome::Accepted, $providerMessageId);
    }

    public static function transient(string $reason): self
    {
        return new self(GatewayOutcome::Transient, reason: $reason);
    }

    public static function permanent(string $reason): self
    {
        return new self(GatewayOutcome::Permanent, reason: $reason);
    }
}
