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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'datajud' => [
        'base_url' => env('DATAJUD_BASE_URL', 'https://api-publica.datajud.cnj.jus.br'),
        'api_key' => env('DATAJUD_API_KEY', 'cDZHYzlZa0JadVREZDJCendQbXY6SkJlTzNjLV9TRENyQk1RdnFKZGRQdw=='),
    ],

    'fcm' => [
        'enabled' => env('FCM_ENABLED', env('SERVICES_FCM_ENABLED', false)),
        'project_id' => env('FCM_PROJECT_ID', env('SERVICES_FCM_PROJECT_ID', '')),
        'service_account_json_base64' => env(
            'FCM_SERVICE_ACCOUNT_JSON_BASE64',
            env('SERVICES_FCM_SERVICE_ACCOUNT_JSON_BASE64', ''),
        ),
    ],

    // Integracao da Agenda com Google Calendar (Fase 2 - push uma-via).
    'google_calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET', ''),
        // Fase 3 (sincronizacao bidirecional via webhooks). Desligado por padrao.
        'webhooks_enabled' => env('GOOGLE_CALENDAR_WEBHOOKS_ENABLED', false),
        // Importar eventos criados diretamente no calendario externo (cria compromissos no Ancora).
        'import_external' => env('GOOGLE_CALENDAR_IMPORT_EXTERNAL', false),
    ],

    // Integracao da Agenda com Microsoft 365 / Outlook (Microsoft Graph).
    'microsoft_calendar' => [
        'client_id' => env('MICROSOFT_CALENDAR_CLIENT_ID', ''),
        'client_secret' => env('MICROSOFT_CALENDAR_CLIENT_SECRET', ''),
        // 'common' aceita contas pessoais e de organizacao; use o tenant id para restringir.
        'tenant' => env('MICROSOFT_CALENDAR_TENANT', 'common'),
        'webhooks_enabled' => env('MICROSOFT_CALENDAR_WEBHOOKS_ENABLED', false),
        'import_external' => env('MICROSOFT_CALENDAR_IMPORT_EXTERNAL', false),
    ],

];
