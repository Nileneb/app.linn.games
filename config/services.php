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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'langdock' => [
        'base_url' => env('LANGDOCK_BASE_URL', 'https://api.langdock.com/agent/v1/chat/completions'),
        'api_key' => env('LANGDOCK_API_KEY'),
        'agent_id' => env('LANGDOCK_AGENT_ID'),
        'scoping_mapping_agent' => env('SCOPING_MAPPING_AGENT'),
        'search_agent' => env('SEARCH_AGENT'),
        'review_agent' => env('REVIEW_AGENT'),
        'retrieval_agent' => env('RESEARCH_RETRIEVAL_AGENT'),
    ],

    'mcp' => [
        'auth_token' => env('MCP_AUTH_TOKEN'),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    ],

];
