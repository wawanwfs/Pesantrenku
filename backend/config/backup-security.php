<?php

return [
    'provider' => env('PESANTRENKU_BACKUP_KEY_PROVIDER', env('APP_ENV', 'production') === 'production' ? 'kms' : 'local'),
    'kms_key_id' => env('AWS_KMS_BACKUP_KEY_ID'),
    'kms_endpoint' => env('AWS_KMS_ENDPOINT'),
    'required_approvals' => (int) env('PESANTRENKU_RESTORE_REQUIRED_APPROVALS', 2),
    'request_ttl_minutes' => (int) env('PESANTRENKU_RESTORE_REQUEST_TTL_MINUTES', 60),
];
