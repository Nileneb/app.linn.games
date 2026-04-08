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
        'base_url'     => env('LANGDOCK_BASE_URL', 'https://api.langdock.com/agent/v1/chat/completions'),
        'get_url'      => env('LANGDOCK_GET_URL', 'https://api.langdock.com/agent/v1/get'),
        'update_url'   => env('LANGDOCK_UPDATE_URL', 'https://api.langdock.com/agent/v1/update'),
        // MCP-Protokoll-Endpunkt für Chat-Agent-Streaming (LangdockMcpClient)
        'mcp_endpoint' => env('LANGDOCK_MCP_ENDPOINT', 'https://api.langdock.com/mcp/v1'),
        'api_key' => env('LANGDOCK_API_KEY'),
        'webhook_secret' => env('LANGDOCK_WEBHOOK_SECRET'),
        'agent_id' => env('LANGDOCK_AGENT_ID'),
        'scoping_mapping_agent' => env('SCOPING_MAPPING_AGENT'),
        'search_agent' => env('SEARCH_AGENT'),
        'review_agent' => env('REVIEW_AGENT'),
        'retrieval_agent' => env('RESEARCH_RETRIEVAL_AGENT'),
        'evaluation_agent' => env('EVALUATION_AGENT'),
        'pico_agent' => env('PICO_AGENT'),
        'synthesis_agent' => env('SYNTHESIS_AGENT'),
        'mayring_agent' => env('MAYRING_AGENT'),
        'price_per_1k_tokens_cents' => env('LANGDOCK_PRICE_PER_1K_TOKENS_CENTS', 2),
        'low_balance_threshold_percent' => env('LANGDOCK_LOW_BALANCE_THRESHOLD_PERCENT', 10),
        'default_agent_daily_limit_cents' => (int) env('LANGDOCK_DEFAULT_AGENT_DAILY_LIMIT_CENTS', 500),
        'agent_daily_limits' => [
            // Konfigurierbare Tageslimits pro Agent in Cents (0 = kein Limit)
            // Default: 500 Cents = 5 EUR pro Agent/Tag (Schutz vor Token-Explosionen)
            'agent_id'              => (int) env('LANGDOCK_DAILY_LIMIT_DASHBOARD', 500),
            'scoping_mapping_agent' => (int) env('LANGDOCK_DAILY_LIMIT_SCOPING', 500),
            'search_agent'          => (int) env('LANGDOCK_DAILY_LIMIT_SEARCH', 500),
            'review_agent'          => (int) env('LANGDOCK_DAILY_LIMIT_REVIEW', 500),
            'retrieval_agent'       => (int) env('LANGDOCK_DAILY_LIMIT_RETRIEVAL', 500),
            'evaluation_agent'      => (int) env('LANGDOCK_DAILY_LIMIT_EVALUATION', 500),
            'pico_agent'            => (int) env('LANGDOCK_DAILY_LIMIT_PICO', 500),
            'synthesis_agent'       => (int) env('LANGDOCK_DAILY_LIMIT_SYNTHESIS', 500),
            'mayring_agent'         => (int) env('LANGDOCK_DAILY_LIMIT_MAYRING', 500),
        ],
    ],

    'mcp' => [
        'auth_token' => env('MCP_AUTH_TOKEN'),
        'rate_limit' => (int) env('MCP_RATE_LIMIT', 60),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    ],

];
