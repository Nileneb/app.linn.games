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

    'credits' => [
        'starter_amount_cents' => (int) env('CREDITS_STARTER_AMOUNT_CENTS', 100),
    ],

    'langdock' => [
        'base_url' => env('LANGDOCK_BASE_URL', 'https://api.langdock.com/agent/v1/chat/completions'),
        'api_key' => env('LANGDOCK_API_KEY'),
        'agent_id' => env('LANGDOCK_AGENT_ID'),
        'scoping_mapping_agent' => env('SCOPING_MAPPING_AGENT'),
        'search_agent' => env('SEARCH_AGENT'),
        'review_agent' => env('REVIEW_AGENT'),
        'retrieval_agent' => env('RESEARCH_RETRIEVAL_AGENT'),
        'pico_agent' => env('PICO_AGENT'),
        'synthesis_agent' => env('SYNTHESIS_AGENT'),
        'mayring_agent' => env('MAYRING_AGENT'),
        'price_per_1k_tokens_cents' => env('LANGDOCK_PRICE_PER_1K_TOKENS_CENTS', 2),
        'low_balance_threshold_percent' => env('LANGDOCK_LOW_BALANCE_THRESHOLD_PERCENT', 10),
        'agent_daily_limits' => [
            // Konfigurierbare Tageslimits pro Agent in Cents (0 = kein Limit)
            'agent_id'              => (int) env('LANGDOCK_DAILY_LIMIT_DASHBOARD', 0),
            'scoping_mapping_agent' => (int) env('LANGDOCK_DAILY_LIMIT_SCOPING', 0),
            'search_agent'          => (int) env('LANGDOCK_DAILY_LIMIT_SEARCH', 0),
            'review_agent'          => (int) env('LANGDOCK_DAILY_LIMIT_REVIEW', 0),
            'retrieval_agent'       => (int) env('LANGDOCK_DAILY_LIMIT_RETRIEVAL', 0),
            'pico_agent'            => (int) env('LANGDOCK_DAILY_LIMIT_PICO', 0),
            'synthesis_agent'       => (int) env('LANGDOCK_DAILY_LIMIT_SYNTHESIS', 0),
            'mayring_agent'         => (int) env('LANGDOCK_DAILY_LIMIT_MAYRING', 0),
        ],
    ],

    'mcp' => [
        'auth_token' => env('MCP_AUTH_TOKEN'),
        'rate_limit' => (int) env('MCP_RATE_LIMIT', 60),
    ],

    'webhooks' => [
        'rate_limit' => (int) env('WEBHOOKS_RATE_LIMIT', 30),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    ],

];
