<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Anthropic API Key
    |--------------------------------------------------------------------------
    |
    | Your Anthropic API key for authenticating requests to the Claude API.
    |
    */
    'api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Auth Token (Alternative Authentication)
    |--------------------------------------------------------------------------
    |
    | OAuth/bearer token for alternative authentication methods.
    | Use this instead of api_key when using OAuth-based authentication.
    |
    */
    'auth_token' => env('ANTHROPIC_AUTH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Custom API endpoint for proxies or enterprise deployments.
    | Leave null to use the default Anthropic API (https://api.anthropic.com).
    |
    */
    'base_url' => env('ANTHROPIC_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default Claude model to use for conversations.
    |
    */
    'default_model' => env('CLAUDE_MODEL', 'claude-sonnet-4-5-20250929'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum number of seconds to wait for a response.
    |
    */
    'timeout' => env('CLAUDE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Max Retries
    |--------------------------------------------------------------------------
    |
    | Number of times to retry failed requests.
    |
    */
    'max_retries' => 2,

    /*
    |--------------------------------------------------------------------------
    | Beta Features
    |--------------------------------------------------------------------------
    |
    | Enable beta API features. These require specific beta headers.
    |
    */
    'beta_features' => [
        'mcp_connector' => true,      // mcp-client-2025-11-20
        'extended_thinking' => true,
        'prompt_caching' => true,
        'structured_outputs' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pre-configured MCP Servers
    |--------------------------------------------------------------------------
    |
    | Define MCP servers that can be referenced by name in conversations.
    |
    */
    'mcp_servers' => [
        // 'zapier' => [
        //     'url' => env('ZAPIER_MCP_URL'),
        //     'token' => env('ZAPIER_MCP_TOKEN'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Pricing
    |--------------------------------------------------------------------------
    |
    | Pricing per million tokens for cost estimation. Prices in USD.
    | Update these values when Anthropic changes pricing.
    |
    | Format: 'model-pattern' => ['input' => price, 'output' => price]
    |
    */
    'pricing' => [
        'claude-opus' => [
            'input' => 15.00,
            'output' => 75.00,
        ],
        'claude-sonnet' => [
            'input' => 3.00,
            'output' => 15.00,
        ],
        'claude-haiku' => [
            'input' => 0.25,
            'output' => 1.25,
        ],
    ],
];
