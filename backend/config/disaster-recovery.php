<?php

return [
    'maximum_backup_age_hours' => (int) env('PESANTRENKU_DR_MAX_BACKUP_AGE_HOURS', 24),
    'require_pitr_in_production' => (bool) env('PESANTRENKU_DR_REQUIRE_PITR', true),
    'report_prefix' => env('PESANTRENKU_DR_REPORT_PREFIX', 'dr-drills'),
];
