<?php

return [
    'import' => [
        'preview_rows' => (int) env('PESANTRENKU_IMPORT_PREVIEW_ROWS', 5),
        'queue_threshold' => (int) env('PESANTRENKU_IMPORT_QUEUE_THRESHOLD', 500),
        'chunk_size' => (int) env('PESANTRENKU_IMPORT_CHUNK_SIZE', 250),
        'max_kilobytes' => (int) env('PESANTRENKU_IMPORT_MAX_KB', 10240),
        'max_rows' => (int) env('PESANTRENKU_IMPORT_MAX_ROWS', 10000),
        'max_columns' => (int) env('PESANTRENKU_IMPORT_MAX_COLUMNS', 100),
        'max_uncompressed_bytes' => (int) env('PESANTRENKU_IMPORT_MAX_UNCOMPRESSED_BYTES', 52428800),
    ],

    'export' => [
        'sync_limit' => (int) env('PESANTRENKU_EXPORT_SYNC_LIMIT', 1000),
        'queued_limit' => (int) env('PESANTRENKU_EXPORT_QUEUED_LIMIT', 10000),
    ],

    'queue' => [
        'default_worker_timeout' => (int) env('PESANTRENKU_DEFAULT_WORKER_TIMEOUT', 120),
        'long_worker_timeout' => (int) env('PESANTRENKU_LONG_WORKER_TIMEOUT', 900),
        'visibility_margin' => (int) env('PESANTRENKU_QUEUE_VISIBILITY_MARGIN', 60),
    ],

    'backup' => [
        'max_rows' => (int) env('PESANTRENKU_TENANT_BACKUP_MAX_ROWS', 25000),
        'max_plaintext_bytes' => (int) env('PESANTRENKU_TENANT_BACKUP_MAX_BYTES', 52428800),
        'readiness_max_age_hours' => (int) env('PESANTRENKU_BACKUP_READINESS_MAX_AGE_HOURS', 24),
        'restore_execution_timeout_minutes' => (int) env('PESANTRENKU_RESTORE_EXECUTION_TIMEOUT_MINUTES', 30),
        'creation_timeout_minutes' => (int) env('PESANTRENKU_BACKUP_CREATION_TIMEOUT_MINUTES', 20),
        'queue_wait_timeout_minutes' => (int) env('PESANTRENKU_BACKUP_QUEUE_WAIT_TIMEOUT_MINUTES', 60),
        'rotation_lock_seconds' => (int) env('PESANTRENKU_BACKUP_ROTATION_LOCK_SECONDS', 1800),
    ],

    'notifications' => [
        'polling_seconds' => (int) env('PESANTRENKU_NOTIFICATION_POLL_SECONDS', 20),
        'sse_enabled' => (bool) env('PESANTRENKU_NOTIFICATION_SSE', true),
        'websocket_enabled' => (bool) env('PESANTRENKU_NOTIFICATION_WEBSOCKET', false),
        'websocket_origin' => env(
            'PESANTRENKU_WEBSOCKET_ORIGIN',
            (env('REVERB_SCHEME', 'https') === 'https' ? 'wss' : 'ws').'://'.env('REVERB_HOST', 'localhost').':'.env('REVERB_PORT', 443),
        ),
    ],

    'operations' => [
        'file_retention_days' => (int) env('PESANTRENKU_FILE_RETENTION_DAYS', 7),
        'disk' => env('PESANTRENKU_OPERATION_DISK', env('FILESYSTEM_DISK', 'local')),
        'signed_url_minutes' => (int) env('PESANTRENKU_SIGNED_URL_MINUTES', 10),
        'public_endpoint' => env('PESANTRENKU_OPERATION_PUBLIC_ENDPOINT'),
        'processing_timeout_minutes' => (int) env('PESANTRENKU_OPERATION_PROCESSING_TIMEOUT_MINUTES', 20),
        'queue_wait_timeout_minutes' => (int) env('PESANTRENKU_OPERATION_QUEUE_WAIT_TIMEOUT_MINUTES', 60),
        'max_recovery_attempts' => (int) env('PESANTRENKU_OPERATION_MAX_RECOVERY_ATTEMPTS', 3),
    ],

    'observability' => [
        'metrics_token' => env('PESANTRENKU_METRICS_TOKEN'),
        'metric_identity' => [
            'instance' => env('METRICS_INSTANCE_ID', gethostname() ?: php_uname('n')),
            'revision' => env('METRICS_REVISION', env('GITHUB_SHA', 'unknown')),
            'track' => env('METRICS_TRACK', 'stable'),
        ],
        'slow_query_ms' => (float) env('PESANTRENKU_SLOW_QUERY_MS', 100),
        'metric_retention_days' => (int) env('PESANTRENKU_METRIC_RETENTION_DAYS', 14),
        'metric_exporter_lease_seconds' => (int) env('PESANTRENKU_METRIC_EXPORTER_LEASE_SECONDS', 45),
        'alertmanager_url' => env('PESANTRENKU_ALERTMANAGER_URL', 'http://alertmanager:9093/-/ready'),
        'alertmanager_api_url' => env('PESANTRENKU_ALERTMANAGER_API_URL', 'http://alertmanager:9093/api/v2/alerts'),
        'loki_url' => env('PESANTRENKU_LOKI_URL', 'http://loki:3100/ready'),
        'loki_query_url' => env('PESANTRENKU_LOKI_QUERY_URL', 'http://loki:3100/loki/api/v1/query_range'),
        'drill_mail_to' => env('PESANTRENKU_OPERATIONAL_DRILL_MAIL_TO'),
        'drill_confirmation_url' => env('PESANTRENKU_OPERATIONAL_DRILL_CONFIRM_URL'),
        'drill_confirmation_token' => env('PESANTRENKU_OPERATIONAL_DRILL_CONFIRM_TOKEN'),
        'drill_timeout_seconds' => (int) env('PESANTRENKU_OPERATIONAL_DRILL_TIMEOUT_SECONDS', 90),
        'otel_enabled' => (bool) env('PESANTRENKU_OTEL_ENABLED', false),
        'otel' => [
            'service_name' => env('OTEL_SERVICE_NAME', 'pesantenku'),
            'resource_attributes' => env('OTEL_RESOURCE_ATTRIBUTES', 'service.namespace=pesantenku'),
            'traces_exporter' => env('OTEL_TRACES_EXPORTER', 'none'),
            'exporter_protocol' => env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/protobuf'),
            'exporter_endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://127.0.0.1:4318'),
            'propagators' => env('OTEL_PROPAGATORS', 'tracecontext,baggage'),
            'sampler' => env('OTEL_TRACES_SAMPLER', 'parentbased_always_on'),
            'sampler_argument' => env('OTEL_TRACES_SAMPLER_ARG'),
            'processor' => env('OTEL_PHP_TRACES_PROCESSOR', 'batch'),
            'batch_schedule_delay' => (int) env('OTEL_BSP_SCHEDULE_DELAY', 200),
            'batch_export_timeout' => (int) env('OTEL_BSP_EXPORT_TIMEOUT', 1000),
            'batch_max_queue_size' => (int) env('OTEL_BSP_MAX_QUEUE_SIZE', 2048),
            'batch_max_export_size' => (int) env('OTEL_BSP_MAX_EXPORT_BATCH_SIZE', 512),
        ],
    ],

    'entitlements' => [
        'enforced' => (bool) env('PESANTRENKU_ENFORCE_ENTITLEMENTS', env('APP_ENV') === 'production'),
    ],

    'qr' => [
        'challenge_seconds' => (int) env('PESANTRENKU_QR_CHALLENGE_SECONDS', 30),
        'allowed_networks' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PESANTRENKU_QR_ALLOWED_NETWORKS', ''))
        ))),
    ],

    'auth' => [
        'enforce_privileged_mfa' => (bool) env('PESANTRENKU_ENFORCE_PRIVILEGED_MFA', env('APP_ENV') === 'production'),
        'privileged_roles' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PESANTRENKU_MFA_PRIVILEGED_ROLES', 'super_admin,owner_pesantren'))
        ))),
        'bootstrap_password_file' => env('PESANTRENKU_BOOTSTRAP_PASSWORD_FILE'),
        'bootstrap_mfa_secret_file' => env('PESANTRENKU_BOOTSTRAP_MFA_SECRET_FILE'),
        'bootstrap_lock_seconds' => (int) env('PESANTRENKU_BOOTSTRAP_LOCK_SECONDS', 180),
        'mobile_token_minutes' => (int) env('PESANTRENKU_MOBILE_TOKEN_MINUTES', 1440),
        'privileged_mobile_token_minutes' => (int) env('PESANTRENKU_PRIVILEGED_MOBILE_TOKEN_MINUTES', 120),
        'require_mobile_device_binding' => (bool) env('PESANTRENKU_REQUIRE_MOBILE_DEVICE_BINDING', true),
    ],

    'security' => [
        'headers_enabled' => (bool) env('PESANTRENKU_SECURITY_HEADERS', true),
        'enforce_allowed_hosts' => (bool) env('PESANTRENKU_ENFORCE_ALLOWED_HOSTS', env('APP_ENV') === 'production'),
        'allowed_hosts' => array_values(array_filter(array_map(
            fn (string $host) => strtolower(trim($host)),
            explode(',', (string) env('PESANTRENKU_ALLOWED_HOSTS', parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: ''))
        ))),
        'require_idempotency_key' => (bool) env('PESANTRENKU_REQUIRE_IDEMPOTENCY_KEY', false),
        'idempotency_processing_ttl_minutes' => (int) env('PESANTRENKU_IDEMPOTENCY_PROCESSING_TTL_MINUTES', 30),
        'idempotency_completed_ttl_hours' => (int) env('PESANTRENKU_IDEMPOTENCY_COMPLETED_TTL_HOURS', 24),
    ],
];
