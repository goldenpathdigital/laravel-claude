# Laravel Claude

[![Latest Version on Packagist](https://img.shields.io/packagist/v/goldenpathdigital/laravel-claude.svg?style=flat-square)](https://packagist.org/packages/goldenpathdigital/laravel-claude)
[![Total Downloads](https://img.shields.io/packagist/dt/goldenpathdigital/laravel-claude.svg?style=flat-square)](https://packagist.org/packages/goldenpathdigital/laravel-claude)
[![License](https://img.shields.io/packagist/l/goldenpathdigital/laravel-claude.svg?style=flat-square)](https://packagist.org/packages/goldenpathdigital/laravel-claude)

A Laravel wrapper for the official [Anthropic PHP SDK](https://github.com/anthropics/anthropic-sdk-php) with first-class MCP connector support.

## Features

- **Official SDK** — Wraps `anthropic-ai/sdk`, not a custom HTTP implementation
- **Laravel Native** — Facades, config, service provider, auto-discovery
- **Fluent API** — Chainable conversation builder
- **MCP Connector** — First Laravel package with MCP client support
- **Beta Features** — Extended thinking, prompt caching, structured outputs

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require goldenpathdigital/laravel-claude
```

Publish the config file:

```bash
php artisan vendor:publish --tag=claude-config
```

Add your API key to `.env`:

```env
ANTHROPIC_API_KEY=your-api-key
```

## Usage

### Direct SDK Access

```php
use GoldenPathDigital\Claude\Facades\Claude;

$response = Claude::messages()->create([
    'model' => 'claude-sonnet-4-5-20250929',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, Claude!'],
    ],
]);

echo $response->content[0]->text;
```

### Fluent Conversation Builder

```php
use GoldenPathDigital\Claude\Facades\Claude;

$response = Claude::conversation()
    ->model('claude-sonnet-4-5-20250929')
    ->system('You are a helpful assistant.')
    ->user('What is the capital of France?')
    ->maxTokens(1024)
    ->temperature(0.7)
    ->send();

echo $response->content[0]->text;
```

### Multi-turn Conversations

```php
$conversation = Claude::conversation()
    ->system('You are a code reviewer.')
    ->user('Review this function: function add($a, $b) { return $a + $b; }')
    ->send();

// Continue the conversation
$followUp = $conversation
    ->user('What about error handling?')
    ->send();
```

## Configuration

```php
// config/claude.php

return [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'default_model' => env('CLAUDE_MODEL', 'claude-sonnet-4-5-20250929'),
    'timeout' => env('CLAUDE_TIMEOUT', 30),
    'max_retries' => 2,
    
    'beta_features' => [
        'mcp_connector' => true,
        'extended_thinking' => true,
        'prompt_caching' => true,
        'structured_outputs' => true,
    ],
    
    'mcp_servers' => [
        // Pre-configured MCP servers
    ],
];
```

## Coming Soon

- **MCP Connector** — Connect to remote MCP servers via Anthropic's connector API
- **Tool System** — Define and execute tools with automatic handling
- **Streaming** — Real-time streaming with Laravel events
- **Extended Thinking** — Access Claude's reasoning process
- **Prompt Caching** — Reduce costs with cached system prompts
- **Queue Integration** — Process conversations in background jobs
- **Testing Utilities** — `Claude::fake()` with assertion helpers

## Testing

```bash
composer test
```

## Code Style

```bash
composer format
```

## License

MIT License. See [LICENSE](LICENSE) for details.
