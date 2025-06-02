<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activity Logging Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('ACTIVITY_LOGGING_ENABLED', true),

    'queue' => env('ACTIVITY_LOGGING_QUEUE', 'default'),

    'log_console_events' => env('ACTIVITY_LOG_CONSOLE_EVENTS', false),

    'models' => [
        'App\Models\Post',
        'App\Models\Comment',
    ],

    'elasticsearch' => [
        'index' => env('ACTIVITY_LOGGING_INDEX', 'activity-logs'),
        'shards' => env('ACTIVITY_LOGGING_SHARDS', 1),
        'replicas' => env('ACTIVITY_LOGGING_REPLICAS', 1),
    ],

    'sensitive_fields' => [
        'password',
        'password_confirmation',
        'remember_token',
        'api_token',
        'secret',
        'private_key',
        'credit_card_number',
        'ssn',
        'social_security_number',
    ],

    'ignore_fields' => [
        'updated_at',
        'created_at',
        'user_ip',
        'password',
        'remember_token',
        // Add more fields as needed
    ],
];
