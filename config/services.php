<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Inbound authentication used by AlphaGate -> APWS microservice proxy.
    'gateway' => [
        'api_key' => env('APWS_API_KEY', env('X_API_KEY')),
    ],

    // AP Web Service provider configuration (Arquivo da Propaganda).
    'apws' => [
        'base_url' => env('APWS_PROVIDER_BASE_URL', 'https://arquivo.net/apws/'),
        'default_customer_uuid' => env('APWS_DEFAULT_CUSTOMER_UUID'),
        'timeout' => (int) env('APWS_PROVIDER_TIMEOUT', 60),
        'retries' => (int) env('APWS_PROVIDER_RETRIES', 3),
        'retry_sleep_ms' => (int) env('APWS_PROVIDER_RETRY_SLEEP_MS', 2000),
        'api_key' => env('APWS_API_KEY', env('X_API_KEY')),
        'gateway_api_key' => env('APWS_API_KEY', env('X_API_KEY')),
        'media_storage' => [
            'enabled' => filter_var(env('APWS_MEDIA_STORAGE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'disk' => env('APWS_MEDIA_STORAGE_DISK', 'public'),
            'timeout' => (int) env('APWS_MEDIA_STORAGE_TIMEOUT', 90),
            'retries' => (int) env('APWS_MEDIA_STORAGE_RETRIES', 2),
            'retry_sleep_ms' => (int) env('APWS_MEDIA_STORAGE_RETRY_SLEEP_MS', 1500),
            'max_bytes' => (int) env('APWS_MEDIA_STORAGE_MAX_BYTES', 157286400),
        ],
        'sync' => [
            'enabled' => filter_var(env('APWS_SYNC_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'test_mode' => filter_var(env('APWS_SYNC_TEST', false), FILTER_VALIDATE_BOOLEAN),
            'weekdays_only' => filter_var(env('APWS_SYNC_WEEKDAYS_ONLY', true), FILTER_VALIDATE_BOOLEAN),
            'business_start' => env('APWS_SYNC_BUSINESS_START', '08:00'),
            'business_end' => env('APWS_SYNC_BUSINESS_END', '19:00'),
            'minute' => (int) env('APWS_SYNC_MINUTE', 0),
            'window_minutes' => (int) env('APWS_SYNC_WINDOW_MINUTES', 60),
            'max_window_days' => (int) env('APWS_SYNC_MAX_WINDOW_DAYS', 30),
            'initial_t1' => env('APWS_SYNC_INITIAL_T1'),
            'persist_raw' => filter_var(env('APWS_SYNC_PERSIST_RAW', true), FILTER_VALIDATE_BOOLEAN),
            'timezone' => env('APWS_SYNC_TIMEZONE', 'America/Sao_Paulo'),
            'customer_uuids' => array_values(array_filter(array_map(
                static fn (string $value): string => trim($value),
                explode(',', (string) env('APWS_SYNC_CUSTOMER_UUIDS', (string) env('APWS_DEFAULT_CUSTOMER_UUID', '')))
            ))),
        ],
    ],

];
