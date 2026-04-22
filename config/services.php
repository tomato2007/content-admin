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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'publisher_driver' => env('TELEGRAM_PUBLISHER_DRIVER', 'null'),
        'runtime_connection' => env('TELEGRAM_RUNTIME_CONNECTION', 'telegram_runtime'),
        'channel_config_table' => env('TELEGRAM_CHANNEL_CONFIG_TABLE', 'telegram_channel_configs'),
        'publish_script_path' => env('TELEGRAM_PUBLISH_SCRIPT_PATH'),
        'bot_api_base_url' => env('TELEGRAM_BOT_API_BASE_URL', 'https://api.telegram.org'),
        'bot_api_timeout' => (int) env('TELEGRAM_BOT_API_TIMEOUT', 10),
    ],

];
