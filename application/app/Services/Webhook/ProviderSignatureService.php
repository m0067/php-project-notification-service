<?php

declare(strict_types=1);

namespace App\Services\Webhook;

final readonly class ProviderSignatureService
{
    public function __construct(private string $secret) {}

    public function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }

    public function verify(string $payload, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($payload), $signature);
    }
}
