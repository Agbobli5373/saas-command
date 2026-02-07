<?php

return [
    'failed_jobs' => [
        'warning_threshold' => env('OPS_FAILED_JOBS_WARNING_THRESHOLD', 1),
    ],

    'stripe_webhooks' => [
        'stale_minutes' => env('OPS_STRIPE_WEBHOOK_STALE_MINUTES', 10),
        'failure_lookback_hours' => env('OPS_STRIPE_WEBHOOK_FAILURE_LOOKBACK_HOURS', 24),
    ],

    'backup' => [
        'health_file' => env('OPS_BACKUP_HEALTH_FILE') ?: storage_path('app/backups/.latest_success'),
        'max_age_hours' => env('OPS_BACKUP_MAX_AGE_HOURS', 26),
    ],
];
