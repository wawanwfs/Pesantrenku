#!/usr/bin/env sh
set -eu

ENV_FILE="${PESANTRENKU_ENV_FILE:-./backend/.env.production}"
COMPOSE_FILE="${PESANTRENKU_COMPOSE_FILE:-./docker-compose.production.yml}"
STATE_DIR="${PESANTRENKU_RELEASE_STATE_DIR:-./.pesantenku-release-state}"
mkdir -p "$STATE_DIR"

RUNTIME_ENV_DIR=$(mktemp -d "${TMPDIR:-/tmp}/pesantenku-runtime-env.XXXXXX")
cleanup_runtime_env() {
    rm -rf "$RUNTIME_ENV_DIR"
}

ROLLBACK_REQUIRED=0
PREVIOUS_IMAGE=""
rollback_release() {
    status=$?
    if [ "$ROLLBACK_REQUIRED" -eq 1 ] && [ -n "$PREVIOUS_IMAGE" ]; then
        echo "Deployment gagal; mengembalikan seluruh runtime ke digest sebelumnya: $PREVIOUS_IMAGE" >&2
        rollback_ok=1
        if ! PESANTRENKU_IMAGE="$PREVIOUS_IMAGE" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --wait \
            app queue-default queue-backup queue-import queue-export scheduler reverb; then
            echo "ROLLBACK FAILED: Docker Compose tidak dapat memulihkan runtime sebelumnya." >&2
            rollback_ok=0
        fi

        for service in app queue-default queue-backup queue-import queue-export scheduler reverb; do
            container=$(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" ps -q "$service" 2>/dev/null || true)
            if [ -z "$container" ]; then
                echo "ROLLBACK VERIFICATION FAILED: service tidak berjalan: $service" >&2
                rollback_ok=0
                continue
            fi
            actual=$(docker inspect -f '{{.Config.Image}}' "$container" 2>/dev/null || true)
            if [ "$actual" != "$PREVIOUS_IMAGE" ]; then
                echo "ROLLBACK VERIFICATION FAILED: $service uses '$actual', expected '$PREVIOUS_IMAGE'" >&2
                rollback_ok=0
            fi
        done

        if [ -n "${DOMAIN:-}" ]; then
            attempt=0
            while ! curl --fail --silent --show-error "https://${DOMAIN}/health" >/dev/null 2>&1; do
                attempt=$((attempt + 1))
                if [ "$attempt" -ge 12 ]; then
                    echo "ROLLBACK VERIFICATION FAILED: health publik tidak pulih pada https://${DOMAIN}/health" >&2
                    rollback_ok=0
                    break
                fi
                sleep 5
            done
        fi

        if [ "$rollback_ok" -eq 1 ]; then
            printf '%s\n' "$PREVIOUS_IMAGE" > "$STATE_DIR/current-image"
            echo "Rollback terverifikasi untuk seluruh runtime: $PREVIOUS_IMAGE" >&2
        else
            printf '%s\n' "$PREVIOUS_IMAGE" > "$STATE_DIR/rollback-verification-failed"
        fi
    fi
    cleanup_runtime_env
    exit "$status"
}
trap rollback_release EXIT HUP INT TERM

if [ ! -f "$ENV_FILE" ]; then
    echo "Production env file not found: $ENV_FILE" >&2
    exit 1
fi

env_value() {
    sed -n "s/^${1}=//p" "$ENV_FILE" | tail -n 1 | sed 's/^"//; s/"$//'
}

write_runtime_env() {
    destination="$1"
    allowed_sensitive="$2"
    sensitive_keys="APP_KEY DB_PASSWORD REDIS_PASSWORD MAIL_USERNAME MAIL_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_KMS_BACKUP_KEY_ID REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET PESANTRENKU_METRICS_TOKEN MYSQL_ROOT_PASSWORD GF_SECURITY_ADMIN_PASSWORD PESANTRENKU_BOOTSTRAP_ADMIN_EMAIL PESANTRENKU_BOOTSTRAP_PASSWORD_FILE PESANTRENKU_BOOTSTRAP_MFA_SECRET_FILE PESANTRENKU_OPERATIONAL_DRILL_CONFIRM_TOKEN"
    awk -v sensitive="$sensitive_keys" -v allowed="$allowed_sensitive" '
        BEGIN {
            split(sensitive, values, " ");
            for (i in values) secret[values[i]] = 1;
            split(allowed, values, " ");
            for (i in values) permit[values[i]] = 1;
        }
        /^[[:space:]]*($|#)/ { next }
        {
            key=$0;
            sub(/=.*/, "", key);
            if (secret[key] && !permit[key]) next;
            print $0;
        }
    ' "$ENV_FILE" > "$destination"
    chmod 600 "$destination"
}

write_runtime_env "$RUNTIME_ENV_DIR/app.env" "APP_KEY DB_PASSWORD REDIS_PASSWORD MAIL_USERNAME MAIL_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET PESANTRENKU_METRICS_TOKEN PESANTRENKU_OPERATIONAL_DRILL_CONFIRM_TOKEN"
write_runtime_env "$RUNTIME_ENV_DIR/default-worker.env" "APP_KEY DB_PASSWORD REDIS_PASSWORD MAIL_USERNAME MAIL_PASSWORD REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET"
write_runtime_env "$RUNTIME_ENV_DIR/backup-worker.env" "APP_KEY DB_PASSWORD REDIS_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_KMS_BACKUP_KEY_ID"
write_runtime_env "$RUNTIME_ENV_DIR/import-worker.env" "APP_KEY DB_PASSWORD REDIS_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY"
write_runtime_env "$RUNTIME_ENV_DIR/export-worker.env" "APP_KEY DB_PASSWORD REDIS_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY"
write_runtime_env "$RUNTIME_ENV_DIR/scheduler.env" "APP_KEY DB_PASSWORD REDIS_PASSWORD MAIL_USERNAME MAIL_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET"
write_runtime_env "$RUNTIME_ENV_DIR/reverb.env" "APP_KEY REDIS_PASSWORD REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET"
write_runtime_env "$RUNTIME_ENV_DIR/operations.env" "APP_KEY DB_PASSWORD REDIS_PASSWORD MAIL_USERNAME MAIL_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_KMS_BACKUP_KEY_ID REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET PESANTRENKU_METRICS_TOKEN PESANTRENKU_OPERATIONAL_DRILL_CONFIRM_TOKEN"

export PESANTRENKU_APP_ENV_FILE="$RUNTIME_ENV_DIR/app.env"
export PESANTRENKU_DEFAULT_WORKER_ENV_FILE="$RUNTIME_ENV_DIR/default-worker.env"
export PESANTRENKU_BACKUP_WORKER_ENV_FILE="$RUNTIME_ENV_DIR/backup-worker.env"
export PESANTRENKU_IMPORT_WORKER_ENV_FILE="$RUNTIME_ENV_DIR/import-worker.env"
export PESANTRENKU_EXPORT_WORKER_ENV_FILE="$RUNTIME_ENV_DIR/export-worker.env"
export PESANTRENKU_SCHEDULER_ENV_FILE="$RUNTIME_ENV_DIR/scheduler.env"
export PESANTRENKU_REVERB_ENV_FILE="$RUNTIME_ENV_DIR/reverb.env"
export PESANTRENKU_OPERATIONS_ENV_FILE="$RUNTIME_ENV_DIR/operations.env"

required_keys="APP_KEY PESANTRENKU_IMAGE CADDY_IMAGE MYSQL_IMAGE REDIS_IMAGE OTEL_COLLECTOR_IMAGE TEMPO_IMAGE PROMETHEUS_IMAGE ALERTMANAGER_IMAGE LOKI_IMAGE PROMTAIL_IMAGE GRAFANA_IMAGE ALERTMANAGER_WEBHOOK_URL PESANTRENKU_COSIGN_CERTIFICATE_IDENTITY PESANTRENKU_COSIGN_CERTIFICATE_OIDC_ISSUER PRIMARY_DOMAIN PESANTRENKU_ALLOWED_HOSTS ACME_EMAIL DB_PASSWORD MYSQL_ROOT_PASSWORD REDIS_PASSWORD REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET GF_SECURITY_ADMIN_PASSWORD PESANTRENKU_METRICS_TOKEN AWS_BUCKET AWS_KMS_BACKUP_KEY_ID MAIL_MAILER MAIL_FROM_ADDRESS PESANTRENKU_OPERATIONAL_DRILL_MAIL_TO PESANTRENKU_OPERATIONAL_DRILL_CONFIRM_URL PESANTRENKU_OPERATIONAL_DRILL_CONFIRM_TOKEN"
for key in $required_keys; do
    value=$(env_value "$key")
    if [ -z "$value" ]; then
        echo "Required production value is empty: $key" >&2
        exit 1
    fi
done

assert_digest_image() {
    key="$1"
    value=$(env_value "$key")
    case "$value" in
        *@sha256:????????????????????????????????????????????????????????????????) ;;
        *)
            echo "$key must use an immutable sha256 digest, not a tag: $value" >&2
            exit 1
            ;;
    esac
    case "$value" in
        *REPLACE_WITH*|*example*)
            echo "$key still contains a placeholder: $value" >&2
            exit 1
            ;;
    esac
}

for key in PESANTRENKU_IMAGE CADDY_IMAGE MYSQL_IMAGE REDIS_IMAGE OTEL_COLLECTOR_IMAGE TEMPO_IMAGE PROMETHEUS_IMAGE ALERTMANAGER_IMAGE LOKI_IMAGE PROMTAIL_IMAGE GRAFANA_IMAGE; do
    assert_digest_image "$key"
done
IMAGE=$(env_value PESANTRENKU_IMAGE)
PESANTRENKU_METRICS_REVISION=$(printf '%s' "$IMAGE" | sed 's/.*@sha256://' | cut -c1-12)
export PESANTRENKU_METRICS_REVISION

if ! command -v cosign >/dev/null 2>&1; then
    echo "Cosign is required to verify the production image before deployment." >&2
    exit 1
fi
COSIGN_IDENTITY=$(env_value PESANTRENKU_COSIGN_CERTIFICATE_IDENTITY)
COSIGN_ISSUER=$(env_value PESANTRENKU_COSIGN_CERTIFICATE_OIDC_ISSUER)
cosign verify \
    --certificate-identity "$COSIGN_IDENTITY" \
    --certificate-oidc-issuer "$COSIGN_ISSUER" \
    "$IMAGE" >/dev/null
cosign verify-attestation \
    --certificate-identity "$COSIGN_IDENTITY" \
    --certificate-oidc-issuer "$COSIGN_ISSUER" \
    --type slsaprovenance "$IMAGE" >/dev/null
cosign verify-attestation \
    --certificate-identity "$COSIGN_IDENTITY" \
    --certificate-oidc-issuer "$COSIGN_ISSUER" \
    --type spdxjson "$IMAGE" >/dev/null

assert_strong_secret() {
    key="$1"
    minimum="$2"
    value=$(env_value "$key")
    normalized=$(printf '%s' "$value" | tr '[:upper:]' '[:lower:]')
    case "$normalized" in
        ''|password|password123|secret|change-me|changeme|admin|pesantenku)
            echo "Weak or missing production secret: $key" >&2
            exit 1
            ;;
    esac
    if [ "${#value}" -lt "$minimum" ]; then
        echo "Production secret is shorter than ${minimum} characters: $key" >&2
        exit 1
    fi
}

assert_strong_secret DB_PASSWORD 16
assert_strong_secret MYSQL_ROOT_PASSWORD 16
assert_strong_secret REDIS_PASSWORD 32
assert_strong_secret REVERB_APP_KEY 16
assert_strong_secret REVERB_APP_SECRET 32
assert_strong_secret GF_SECURITY_ADMIN_PASSWORD 16
assert_strong_secret PESANTRENKU_METRICS_TOKEN 32

MAILER=$(env_value MAIL_MAILER)
case "$MAILER" in
    smtp)
        for key in MAIL_HOST MAIL_USERNAME MAIL_PASSWORD; do
            value=$(env_value "$key")
            if [ -z "$value" ]; then
                echo "SMTP transport requires a non-empty $key" >&2
                exit 1
            fi
        done
        assert_strong_secret MAIL_PASSWORD 12
        ;;
    log|array)
        echo "MAIL_MAILER cannot use $MAILER in production" >&2
        exit 1
        ;;
esac

ALERT_WEBHOOK=$(env_value ALERTMANAGER_WEBHOOK_URL)
case "$ALERT_WEBHOOK" in
    https://*) ;;
    *) echo "ALERTMANAGER_WEBHOOK_URL must use HTTPS" >&2; exit 1 ;;
esac

DOMAIN=$(env_value PRIMARY_DOMAIN)
ALLOWED_HOSTS=$(env_value PESANTRENKU_ALLOWED_HOSTS)
case ",$ALLOWED_HOSTS," in
    *",$DOMAIN,"*) ;;
    *)
        echo "PESANTRENKU_ALLOWED_HOSTS must include PRIMARY_DOMAIN: $DOMAIN" >&2
        exit 1
        ;;
esac
case ",$ALLOWED_HOSTS," in
    *",app,"*) ;;
    *)
        echo "PESANTRENKU_ALLOWED_HOSTS must include the internal app hostname for Prometheus scraping." >&2
        exit 1
        ;;
esac
case "$DOMAIN" in
    localhost|127.0.0.1|*.localhost|*.example|*.test|*://*|*/*)
        echo "PRIMARY_DOMAIN is not a valid public production hostname: $DOMAIN" >&2
        exit 1
        ;;
esac

export PESANTRENKU_ENV_FILE="$ENV_FILE"

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config --quiet
CURRENT_APP_CONTAINER=$(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" ps -q app 2>/dev/null || true)
if [ -n "$CURRENT_APP_CONTAINER" ]; then
    PREVIOUS_IMAGE=$(docker inspect -f '{{.Config.Image}}' "$CURRENT_APP_CONTAINER" 2>/dev/null || true)
fi
if [ -n "$PREVIOUS_IMAGE" ]; then
    case "$PREVIOUS_IMAGE" in
        *@sha256:????????????????????????????????????????????????????????????????) ;;
        *)
            echo "Runtime lama tidak memakai digest immutable: $PREVIOUS_IMAGE" >&2
            exit 1
            ;;
    esac
    printf '%s\n' "$PREVIOUS_IMAGE" > "$STATE_DIR/previous-image"
fi
printf '%s\n' "$IMAGE" > "$STATE_DIR/target-image"

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config --quiet
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" pull
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --wait mysql redis
docker compose --profile operations --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm operations php artisan pesantenku:production-check --skip-database --skip-runtime-probes
docker compose --profile operations --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm operations php artisan pesantenku:domain-preflight
docker compose --profile operations --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm operations php artisan migrate --force --isolated
BOOTSTRAP_ADMIN_EMAIL=$(env_value PESANTRENKU_BOOTSTRAP_ADMIN_EMAIL)
if [ -n "$BOOTSTRAP_ADMIN_EMAIL" ]; then
    BOOTSTRAP_PASSWORD_FILE=$(env_value PESANTRENKU_BOOTSTRAP_PASSWORD_FILE)
    BOOTSTRAP_MFA_SECRET_FILE=$(env_value PESANTRENKU_BOOTSTRAP_MFA_SECRET_FILE)
    if [ -z "$BOOTSTRAP_MFA_SECRET_FILE" ] || [ ! -r "$BOOTSTRAP_MFA_SECRET_FILE" ]; then
        echo "Bootstrap MFA secret file is missing or unreadable: $BOOTSTRAP_MFA_SECRET_FILE" >&2
        exit 1
    fi
    if [ -n "$BOOTSTRAP_PASSWORD_FILE" ] && [ ! -r "$BOOTSTRAP_PASSWORD_FILE" ]; then
        echo "Bootstrap password secret file is unreadable: $BOOTSTRAP_PASSWORD_FILE" >&2
        exit 1
    fi
    BOOTSTRAP_MFA_SECRET_FILE=$(cd "$(dirname "$BOOTSTRAP_MFA_SECRET_FILE")" && pwd)/$(basename "$BOOTSTRAP_MFA_SECRET_FILE")
    set -- docker compose --profile operations --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm
    if [ -n "$BOOTSTRAP_PASSWORD_FILE" ]; then
        BOOTSTRAP_PASSWORD_FILE=$(cd "$(dirname "$BOOTSTRAP_PASSWORD_FILE")" && pwd)/$(basename "$BOOTSTRAP_PASSWORD_FILE")
        set -- "$@" -v "$BOOTSTRAP_PASSWORD_FILE:/run/secrets/pesantenku_bootstrap_password:ro"
    fi
    set -- "$@" -v "$BOOTSTRAP_MFA_SECRET_FILE:/run/secrets/pesantenku_bootstrap_mfa:ro" \
        operations php artisan pesantenku:bootstrap-admin "$BOOTSTRAP_ADMIN_EMAIL" \
        --mfa-secret-file=/run/secrets/pesantenku_bootstrap_mfa --if-missing
    if [ -n "$BOOTSTRAP_PASSWORD_FILE" ]; then
        set -- "$@" --password-file=/run/secrets/pesantenku_bootstrap_password
    fi
    "$@"
fi
docker compose --profile operations --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm operations php artisan pesantenku:production-check --skip-runtime-probes
docker compose --profile operations --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm operations php artisan pesantenku:domain-preflight
docker compose --profile operations --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm operations php artisan optimize

# Drain long-running consumers before replacing their containers. Their
# stop_grace_period is deliberately greater than the maximum job timeout.
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" stop \
    queue-default queue-backup queue-import queue-export scheduler reverb || true
ROLLBACK_REQUIRED=1
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --wait \
    app queue-default queue-backup queue-import queue-export scheduler reverb caddy otel-collector tempo prometheus alertmanager loki promtail grafana

attempt=0
until curl --fail --silent --show-error "https://${DOMAIN}/health" >/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 24 ]; then
        echo "Public HTTPS health check failed for https://${DOMAIN}/health" >&2
        exit 1
    fi
    sleep 5
done

for service in app queue-default queue-backup queue-import queue-export scheduler reverb; do
    container=$(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" ps -q "$service")
    [ -n "$container" ] || { echo "Service tidak berjalan setelah rollout: $service" >&2; exit 1; }
    actual=$(docker inspect -f '{{.Config.Image}}' "$container")
    [ "$actual" = "$IMAGE" ] || { echo "Digest $service tidak sesuai: $actual" >&2; exit 1; }
done

docker compose --profile operations --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm operations php artisan pesantenku:production-check
docker compose --profile operations --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm operations php artisan pesantenku:operational-drill
printf '%s\n' "$IMAGE" > "$STATE_DIR/current-image"
ROLLBACK_REQUIRED=0

echo "Pesantrenku is available at https://${DOMAIN} using $IMAGE"
