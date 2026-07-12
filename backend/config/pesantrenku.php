<?php

return [
    'import' => [
        'preview_rows' => (int) env('PESANTRENKU_IMPORT_PREVIEW_ROWS', 5),
        'queue_threshold' => (int) env('PESANTRENKU_IMPORT_QUEUE_THRESHOLD', 500),
        'chunk_size' => (int) env('PESANTRENKU_IMPORT_CHUNK_SIZE', 250),
    ],

    'export' => [
        'sync_limit' => (int) env('PESANTRENKU_EXPORT_SYNC_LIMIT', 1000),
        'queued_limit' => (int) env('PESANTRENKU_EXPORT_QUEUED_LIMIT', 10000),
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
    ],

    'observability' => [
        'metrics_token' => env('PESANTRENKU_METRICS_TOKEN'),
        'slow_query_ms' => (float) env('PESANTRENKU_SLOW_QUERY_MS', 100),
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

    'security' => [
        'headers_enabled' => (bool) env('PESANTRENKU_SECURITY_HEADERS', true),
        'require_idempotency_key' => (bool) env('PESANTRENKU_REQUIRE_IDEMPOTENCY_KEY', false),
    ],
];
