<?php

// M3-15 MCP Server 授权 + AI Agent 授权

return [
    'mcp' => [
        'server_ttl_hours' => 720,
        'max_servers_per_tenant' => 50,
        'allowed_protocols' => ['stdio', 'sse', 'websocket'],
        'capabilities' => [
            'tools', 'resources', 'prompts', 'sampling',
        ],
        'rate_limit' => [
            'requests_per_minute' => 100,
            'tokens_per_minute' => 10000,
        ],
    ],

    'ai_agent' => [
        'max_agents_per_tenant' => 20,
        'allowed_frameworks' => ['langchain', 'autogpt', 'crewai', 'dify', 'custom'],
        'token_quota' => [
            'default_monthly' => 1000000,
            'reset_day' => 1,
        ],
        'auth_methods' => ['api_key', 'oauth', 'jwt'],
        'webhook' => [
            'max_retries' => 3,
            'timeout_seconds' => 10,
        ],
    ],
];
