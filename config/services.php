<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

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

    // KPI review reports: Claude reads tasksheet plans/results (KpiReportController).
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('KPI_REVIEW_MODEL', 'claude-sonnet-5-5'),
        'effort' => env('KPI_REVIEW_EFFORT', 'medium'),
        // Tasksheet "Add to board": splits a day's plan into board tasks.
        'board_tasks_model' => env('BOARD_TASKS_MODEL', 'claude-sonnet-5-5'),
        'board_tasks_effort' => env('BOARD_TASKS_EFFORT', 'low'),
        // "Enhance with Claude" on notes and meeting notes.
        'enhance_model' => env('ENHANCE_MODEL', 'claude-sonnet-5-5'),
        'enhance_effort' => env('ENHANCE_EFFORT', 'low'),
        // Calendar "Import from file": extracts events from uploaded documents.
        'calendar_import_model' => env('CALENDAR_IMPORT_MODEL', 'claude-opus-5-5'),
        'calendar_import_effort' => env('CALENDAR_IMPORT_EFFORT', 'medium'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
