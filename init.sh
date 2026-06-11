#!/usr/bin/env bash
set -e

[ -f .env ] || cp .env.example .env

docker compose up -d --build --remove-orphans

docker compose exec -T notifications_application composer install
docker compose exec -T notifications_application sh -c '[ -f .env ] || cp .env.example .env && php artisan key:generate'
docker compose exec -T notifications_application sh -c \
  '[ -f .env.testing ] || { cp .env.testing.example .env.testing && php artisan key:generate --env=testing; }'

echo "Waiting for PostgreSQL (primary)..."
until docker compose exec -T notifications_postgresql pg_isready -U "${POSTGRES_USER:-notifications}" -q; do
  sleep 1
done

echo "Waiting for PostgreSQL (read replica)..."
until docker compose exec -T notifications_postgresql_replica \
  pg_isready -U "${POSTGRES_USER:-notifications}" -d "${POSTGRES_DB:-notifications}" -q; do
  sleep 1
done

docker compose exec -T notifications_application php artisan migrate --force
docker compose exec -T notifications_application php artisan migrate --env=testing --force

docker compose exec -T notifications_application php artisan l5-swagger:generate

for queue in \
  notifications_sms_high notifications_sms_low \
  notifications_email_high notifications_email_low; do
  docker compose exec -T notifications_application php artisan rabbitmq:queue-declare "$queue"
done

echo "Done. App: http://localhost:10376 | Swagger UI: http://localhost:10376/api/documentation | RabbitMQ UI: http://localhost:10374"
