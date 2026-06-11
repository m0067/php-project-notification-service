# Notification Service

Asynchronous microservice for bulk **SMS / Email** notifications: priority delivery,
provider webhooks (delivery receipts), request idempotency, automatic retries
with backoff, and Redis-backed rate limiting. Built with Laravel, PostgreSQL,
RabbitMQ and Redis — the whole stack starts with a single command.

## Quick start (Docker Compose)

**Prerequisites:** Docker and Docker Compose.

Run:

```bash
make init
```
or
```bash
./init.sh
```

## Testing

Run:

```bash
docker compose exec notifications_application php artisan test
```

## Tech stack

| Component | Technology |
|-----------|------------|
| Language / framework | PHP 8.4, Laravel 13 |
| Database | PostgreSQL 17 |
| Message broker | RabbitMQ 4.0 |
| Cache / locks / rate limiting | Redis 7 |
| Web server | Nginx + PHP-FPM |
| Orchestration | Docker Compose |

### Endpoints & ports

| What | URL |
|------|-----|
| API base | http://localhost:10376/api/v1 |
| Swagger UI | http://localhost:10376/api/documentation |
| RabbitMQ management | http://localhost:10374 (user/pass: `notifications` / `notifications`) |
| PostgreSQL (primary) | `localhost:10370` |
| PostgreSQL (read replica) | `localhost:10371` |
| Redis | `localhost:10372` |

## API

**Send a bulk notification:**

```bash
curl -X POST http://localhost:10376/api/v1/notifications/bulk \
  -H 'Content-Type: application/json' \
  -H 'Request-Id: 3f2504e0-4f89-41d3-9a0c-0305e82c3303' \
  -d '{
    "channel": "sms",
    "message": "Your access code is 0451",
    "recipients": ["1001", "1002"],
    "is_transactional": true
  }'
```

**Subscriber history (cursor-paginated — `per_page`, `cursor`):**

```bash
curl 'http://localhost:10376/api/v1/notifications/subscriber/1001?per_page=25'
```

**Delivery receipt (what a provider sends back):** the raw body is signed with
`HMAC-SHA256(body, PROVIDER_WEBHOOK_SECRET)` in the `X-Provider-Signature` header.

```bash
BODY='{"status":"delivered"}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "local-provider-secret" | awk '{print $2}')

curl -X POST http://localhost:10376/api/v1/notifications/1/delivery-receipt \
  -H 'Content-Type: application/json' \
  -H "X-Provider-Signature: $SIG" \
  -d "$BODY"
```

## Features

- **Bulk dispatch** — one API call sends a message to many recipients over SMS or Email.
- **Traffic prioritization** — transactional messages (access codes, urgent alerts) jump
  ahead of marketing via dedicated high-priority queues.
- **Delivery status & history** — query the full lifecycle of a subscriber's notifications.
- **Provider webhooks** — providers asynchronously confirm delivery; the callback is
  HMAC-signed and idempotent.
- **Request idempotency** — request-level deduplication by the `Request-Id` header (idempotency
  key, Redis-backed) within a TTL window: a retried request returns the original response instead
  of dispatching again. Delivery itself is at-least-once.
- **Reliable delivery** — at-least-once via RabbitMQ, automatic retries with staged backoff;
  jobs that exhaust their attempts land in `failed_jobs`.
- **Rate limiting** — per-provider, per-second caps shared across all workers (Redis).
- **OpenAPI / Swagger** — interactive API docs out of the box.
