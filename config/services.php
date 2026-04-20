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

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', 'https://www.linn.games/api/auth/callback/github'),
    ],

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

    'anthropic' => [
        'api_key' => env('CLAUDE_API_KEY'),
        // Path to the claude CLI binary — uses project-local node_modules, not the user's global install
        'cli_path' => env('CLAUDE_CLI_PATH', base_path('node_modules/.bin/claude')),
        'use_direct_api' => env('CLAUDE_USE_DIRECT_API', false),
        // Dev: Phase-Worker via lokalem Ollama statt Claude API/CLI
        'use_ollama_workers' => env('CLAUDE_USE_OLLAMA_WORKERS', false),
        'ollama_worker_model' => env('CLAUDE_OLLAMA_WORKER_MODEL', 'llama3.2'),
        'model' => env('CLAUDE_MODEL', 'claude-haiku-4-5-20251001'),
        'max_tokens' => (int) env('CLAUDE_MAX_TOKENS', 8192),
        'retry_attempts' => (int) env('CLAUDE_RETRY_ATTEMPTS', 3),
        'retry_sleep_ms' => (int) env('CLAUDE_RETRY_SLEEP_MS', 500),

        // Config-Key → Prompt-Datei Mapping
        'agents' => [
            'scoping_mapping_agent' => 'mapping-agent',
            'search_agent' => 'pico-agent',
            'review_agent' => 'screening-agent',
            'evaluation_agent' => 'quality-agent',
            'synthesis_agent' => 'synthesis-agent',
            'mayring_agent' => 'mayring-agent',
            'chat-agent' => 'chat-agent',
        ],

        // Pricing: Haiku 4.5 — $0.80/$4.00 per M tokens = 0.08/0.40 Cents per 1K tokens
        'price_per_1k_input_tokens_cents' => (float) env('CLAUDE_INPUT_PRICE_CENTS', 0.08),
        'price_per_1k_output_tokens_cents' => (float) env('CLAUDE_OUTPUT_PRICE_CENTS', 0.40),

        // Markup-Faktoren: API-Kosten × Faktor = User-seitige Kosten
        // Haiku-Worker: 3× (günstiges Modell, höhere Marge), Sonnet Chat: 2× (teurer, moderate Marge)
        'markup_factors' => [
            'scoping_mapping_agent' => 3.0,
            'search_agent' => 3.0,
            'review_agent' => 3.0,
            'evaluation_agent' => 3.0,
            'synthesis_agent' => 3.0,
            'mayring_agent' => 3.0,
            'chat-agent' => 2.0,
            'default' => 3.0,
        ],

        'low_balance_threshold_percent' => (int) env('CLAUDE_LOW_BALANCE_THRESHOLD_PERCENT', 10),

        // Modell pro Agent-Typ (CLI --model Flag)
        'agent_models' => [
            'scoping_mapping_agent' => env('CLAUDE_WORKER_MODEL', 'claude-haiku-4-5-20251001'),
            'search_agent' => env('CLAUDE_WORKER_MODEL', 'claude-haiku-4-5-20251001'),
            'review_agent' => env('CLAUDE_WORKER_MODEL', 'claude-haiku-4-5-20251001'),
            'evaluation_agent' => env('CLAUDE_WORKER_MODEL', 'claude-haiku-4-5-20251001'),
            'synthesis_agent' => env('CLAUDE_WORKER_MODEL', 'claude-haiku-4-5-20251001'),
            'mayring_agent' => env('CLAUDE_WORKER_MODEL', 'claude-haiku-4-5-20251001'),
            'chat-agent' => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
        ],

        'agent_daily_limits' => [
            'chat-agent' => (int) env('CLAUDE_DAILY_LIMIT_DASHBOARD', 1000),
            'scoping_mapping_agent' => (int) env('CLAUDE_DAILY_LIMIT_SCOPING', 2000),
            'search_agent' => (int) env('CLAUDE_DAILY_LIMIT_SEARCH', 2000),
            'review_agent' => (int) env('CLAUDE_DAILY_LIMIT_REVIEW', 3000),
            'evaluation_agent' => (int) env('CLAUDE_DAILY_LIMIT_EVALUATION', 2000),
            'synthesis_agent' => (int) env('CLAUDE_DAILY_LIMIT_SYNTHESIS', 2000),
            'mayring_agent' => (int) env('CLAUDE_DAILY_LIMIT_MAYRING', 3000),
        ],
    ],

    'pi_agent' => [
        // Pi-Server (MayringCoder pi_server.py) — Dev-Worker-Backend
        'url' => env('PI_AGENT_URL', 'http://host.docker.internal:8091'),
    ],

    'paper_search' => [
        'url' => env('PAPER_SEARCH_URL', 'http://host.docker.internal:8089'),
        'token' => env('PAPER_SEARCH_TOKEN', ''),
        'mcp_url' => env('PAPER_SEARCH_MCP_URL', 'http://mcp-paper-search:8089/mcp'),
    ],

    'mayring_mcp' => [
        'endpoint' => env('MAYRING_MCP_ENDPOINT', 'http://localhost:8090'),
        'auth_token' => env('MAYRING_MCP_AUTH_TOKEN'),
        'timeout' => (int) env('MAYRING_MCP_TIMEOUT', 60),
    ],

    'mayring' => [
        'ui_url' => env('MAYRING_UI_URL', 'https://mcp.linn.games/ui'),
    ],

    'mcp' => [
        'service_token' => env('MCP_SERVICE_TOKEN', env('MCP_AUTH_TOKEN')),
        'rate_limit' => (int) env('MCP_RATE_LIMIT', 60),
        'restrict_to_internal' => (bool) env('MCP_RESTRICT_TO_INTERNAL', true),
    ],

    'jwt' => [
        'private_key' => env('JWT_PRIVATE_KEY'),
        'public_key' => env('JWT_PUBLIC_KEY'),
        'issuer' => env('JWT_ISSUER', 'https://app.linn.games'),
        'audience' => env('JWT_AUDIENCE', 'mayringcoder'),
        'ttl' => (int) env('JWT_TTL_SECONDS', 28800),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    ],

    'retriever' => [
        'top_n_chunks' => (int) env('RETRIEVER_TOP_N_CHUNKS', 3),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => 'eur',
        'mayring_price_id' => env('STRIPE_MAYRING_PRICE_ID'),
        'packages' => [
            ['cents' => 500,   'price_eur' => 500,   'label' => '5 €'],
            ['cents' => 1000,  'price_eur' => 1000,  'label' => '10 €'],
            ['cents' => 2500,  'price_eur' => 2500,  'label' => '25 €'],
            ['cents' => 5000,  'price_eur' => 5000,  'label' => '50 €'],
        ],
    ],

];
