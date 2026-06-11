<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

final class IdempotencyService
{
    private const DEFAULT_LOCK_SECONDS = 10;

    public function find(string $requestId): ?array
    {
        return Cache::get($this->responseKey($requestId));
    }

    public function remember(string $requestId, array $response): void
    {
        Cache::put($this->responseKey($requestId), $response, $this->ttl());
    }

    public function lock(string $requestId, int $seconds = self::DEFAULT_LOCK_SECONDS): Lock
    {
        return Cache::lock($this->prefix().'lock:'.$requestId, $seconds);
    }

    private function ttl(): int
    {
        return (int) config('notifications.idempotency.ttl');
    }

    private function prefix(): string
    {
        return (string) config('notifications.idempotency.prefix');
    }

    private function responseKey(string $requestId): string
    {
        return $this->prefix().'req:'.$requestId;
    }
}
