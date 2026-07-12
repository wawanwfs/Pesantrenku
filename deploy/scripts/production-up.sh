#!/usr/bin/env sh
set -eu

ENV_FILE="${PESANTRENKU_ENV_FILE:-./backend/.env.production}"
COMPOSE_FILE="${PESANTRENKU_COMPOSE_FILE:-./docker-compose.production.yml}"

if [ ! -f "$ENV_FILE" ]; then
    echo "Production env file not found: $ENV_FILE" >&2
    exit 1
fi

required_keys="APP_KEY PESANTRENKU_IMAGE PRIMARY_DOMAIN ACME_EMAIL DB_PASSWORD MYSQL_ROOT_PASSWORD REVERB_APP_KEY REVERB_APP_SECRET GF_SECURITY_ADMIN_PASSWORD PESANTRENKU_METRICS_TOKEN"
for key in $required_keys; do
    value=$(sed -n "s/^${key}=//p" "$ENV_FILE" | tail -n 1)
    if [ -z "$value" ]; then
        echo "Required production value is empty: $key" >&2
        exit 1
    fi
done

DOMAIN=$(sed -n 's/^PRIMARY_DOMAIN=//p' "$ENV_FILE" | tail -n 1)
export PESANTRENKU_ENV_FILE="$ENV_FILE"

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config --quiet
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" pull
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --wait mysql redis
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm app php artisan migrate --force
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --wait

attempt=0
until curl --fail --silent --show-error "https://${DOMAIN}/health" >/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 24 ]; then
        echo "Public HTTPS health check failed for https://${DOMAIN}/health" >&2
        exit 1
    fi
    sleep 5
done

echo "Pesantrenku is available at https://${DOMAIN}"
