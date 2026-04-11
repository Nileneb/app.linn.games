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

    'langdock' => [
        'base_url' => env('LANGDOCK_BASE_URL', 'https://api.langdock.com/agent/v1/chat/completions'),
        'get_url' => env('LANGDOCK_GET_URL', 'https://api.langdock.com/agent/v1/get'),
        'update_url' => env('LANGDOCK_UPDATE_URL', 'https://api.langdock.com/agent/v1/update'),
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
            'agent_id' => (int) env('LANGDOCK_DAILY_LIMIT_DASHBOARD', 500),
            'scoping_mapping_agent' => (int) env('LANGDOCK_DAILY_LIMIT_SCOPING', 500),
            'search_agent' => (int) env('LANGDOCK_DAILY_LIMIT_SEARCH', 500),
            'review_agent' => (int) env('LANGDOCK_DAILY_LIMIT_REVIEW', 500),
            'retrieval_agent' => (int) env('LANGDOCK_DAILY_LIMIT_RETRIEVAL', 500),
            'evaluation_agent' => (int) env('LANGDOCK_DAILY_LIMIT_EVALUATION', 500),
            'pico_agent' => (int) env('LANGDOCK_DAILY_LIMIT_PICO', 500),
            'synthesis_agent' => (int) env('LANGDOCK_DAILY_LIMIT_SYNTHESIS', 500),
            'mayring_agent' => (int) env('LANGDOCK_DAILY_LIMIT_MAYRING', 500),
        ],
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
            'agent_id' => 'chat-agent',
        ],

        // Pricing: Haiku 4.5 — $0.80/$4.00 per M tokens
        'price_per_1k_input_tokens_cents' => (int) env('CLAUDE_INPUT_PRICE_CENTS', 1),
        'price_per_1k_output_tokens_cents' => (int) env('CLAUDE_OUTPUT_PRICE_CENTS', 4),

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
            'agent_id' => (int) env('CLAUDE_DAILY_LIMIT_DASHBOARD', 500),
            'chat-agent' => (int) env('CLAUDE_DAILY_LIMIT_DASHBOARD', 500),
            'scoping_mapping_agent' => (int) env('CLAUDE_DAILY_LIMIT_SCOPING', 500),
            'search_agent' => (int) env('CLAUDE_DAILY_LIMIT_SEARCH', 500),
            'review_agent' => (int) env('CLAUDE_DAILY_LIMIT_REVIEW', 500),
            'evaluation_agent' => (int) env('CLAUDE_DAILY_LIMIT_EVALUATION', 500),
            'synthesis_agent' => (int) env('CLAUDE_DAILY_LIMIT_SYNTHESIS', 500),
            'mayring_agent' => (int) env('CLAUDE_DAILY_LIMIT_MAYRING', 1000),
        ],
    ],

    'pi_agent' => [
        // Pi-Server (MayringCoder pi_server.py) — Dev-Worker-Backend
        'url' => env('PI_AGENT_URL', 'http://host.docker.internal:8091'),
    ],

    'paper_search' => [
        'url' => env('PAPER_SEARCH_URL', 'http://host.docker.internal:8089'),
        'token' => env('PAPER_SEARCH_TOKEN', ''),
    ],

    'mayring_mcp' => [
        'endpoint' => env('MAYRING_MCP_ENDPOINT', 'http://localhost:8090'),
        'auth_token' => env('MAYRING_MCP_AUTH_TOKEN'),
        'timeout' => (int) env('MAYRING_MCP_TIMEOUT', 60),
    ],

    'mcp' => [
        'auth_token' => env('MCP_AUTH_TOKEN'),
        'rate_limit' => (int) env('MCP_RATE_LIMIT', 60),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => 'eur',
        'packages' => [
            ['cents' => 500,   'price_eur' => 500,   'label' => '5 €'],
            ['cents' => 1000,  'price_eur' => 1000,  'label' => '10 €'],
            ['cents' => 2500,  'price_eur' => 2500,  'label' => '25 €'],
            ['cents' => 5000,  'price_eur' => 5000,  'label' => '50 €'],
        ],
    ],

];
