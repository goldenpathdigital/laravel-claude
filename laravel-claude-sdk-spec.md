# Laravel Claude SDK
## Technical Specification & Market Analysis

**Version:** 1.0 Draft  
**Date:** December 28, 2025  
**Author:** Aaron (Cimarron Lumber Company)

---

## Executive Summary

This spec outlines **`laravel-claude`** — a Laravel wrapper for the official Anthropic PHP SDK that fills a gap in the current ecosystem: there is no Laravel-native package specifically optimized for Claude with first-class MCP client support.

### The Opportunity

| What Exists | What's Missing |
|-------------|----------------|
| Official `anthropic-ai/sdk` (beta, no Laravel support) | Laravel facade, config, service provider |
| `mozex/anthropic-laravel` (tracks their own HTTP client) | Wrapper for *official* SDK |
| `prism-php/prism` (multi-provider abstraction) | Claude-specific optimizations |
| Multiple MCP *server* packages | MCP *client* integration |
| No packages supporting MCP connector API | Direct integration with Anthropic's MCP connector |

---

## Market Landscape

### Current PHP/Laravel Claude Packages

| Package | Stars | Installs | Purpose | Gaps |
|---------|-------|----------|---------|------|
| `anthropic-ai/sdk` | 70 | new | Official PHP SDK (beta) | No Laravel integration |
| `mozex/anthropic-php` | 45 | 187k | Community PHP client | Own HTTP layer, lags API |
| `mozex/anthropic-laravel` | 61 | — | Laravel wrapper for above | Not official SDK |
| `prism-php/prism` | ~200 | — | Multi-provider abstraction | Generic, not Claude-optimized |
| `php-mcp/laravel` | 464 | — | MCP server builder | Server only, no client |
| `laravel/mcp` | 589 | — | Official Laravel MCP servers | Server only, no client |

### Key Insight

The ecosystem has excellent **MCP server** tooling but zero **MCP client** packages for Laravel. The new Anthropic MCP connector API (beta header `mcp-client-2025-11-20`) lets you pass MCP servers directly in API requests — no separate client harness needed.

---

## Package Architecture

### Core Philosophy

```
┌─────────────────────────────────────────────────────────────┐
│                    laravel-claude                           │
├─────────────────────────────────────────────────────────────┤
│  Claude Facade                                              │
│  ├── messages()      → Direct SDK pass-through              │
│  ├── conversation()  → Fluent conversation builder          │
│  ├── tools()         → Tool registration & execution        │
│  └── mcp()           → MCP connector configuration          │
├─────────────────────────────────────────────────────────────┤
│  anthropic-ai/sdk (Official)                                │
└─────────────────────────────────────────────────────────────┘
```

### Design Principles

1. **Thin wrapper** — Don't re-implement the HTTP layer; wrap the official SDK
2. **Laravel-native** — Facades, config, service provider, events, queues
3. **Claude-optimized** — Extended thinking, prompt caching, citations, MCP connector
4. **MCP-first** — First-class support for connecting to remote MCP servers
5. **Testable** — `Claude::fake()` with assertion helpers

---

## Feature Specification

### 1. Installation & Configuration

```bash
composer require acme/laravel-claude
php artisan vendor:publish --tag=claude-config
```

```php
// config/claude.php
return [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'default_model' => env('CLAUDE_MODEL', 'claude-sonnet-4-5-20250929'),
    'timeout' => env('CLAUDE_TIMEOUT', 30),
    'max_retries' => 2,
    
    'beta_features' => [
        'mcp_connector' => true,      // mcp-client-2025-11-20
        'extended_thinking' => true,
        'prompt_caching' => true,
        'structured_outputs' => true,
    ],
    
    'mcp_servers' => [
        // Pre-configured MCP servers
        'zapier' => [
            'url' => env('ZAPIER_MCP_URL'),
            'token' => env('ZAPIER_MCP_TOKEN'),
        ],
    ],
];
```

### 2. Basic Usage (SDK Pass-through)

```php
use Acme\Claude\Facades\Claude;

// Direct SDK access
$response = Claude::messages()->create([
    'model' => 'claude-sonnet-4-5-20250929',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, Claude!'],
    ],
]);

echo $response->content[0]->text;
```

### 3. Fluent Conversation Builder

```php
use Acme\Claude\Facades\Claude;

$response = Claude::conversation()
    ->model('claude-opus-4-5-20251101')
    ->system('You are a helpful assistant for Cimarron Lumber.')
    ->user('What wood species work best for outdoor decking?')
    ->maxTokens(2048)
    ->temperature(0.7)
    ->send();

// Multi-turn
$conversation = Claude::conversation()
    ->system('You are a code reviewer.')
    ->user('Review this function...')
    ->send();

$followUp = $conversation
    ->user('What about error handling?')
    ->send();
```

### 4. Tool System

```php
use Acme\Claude\Facades\Claude;
use Acme\Claude\Tools\Tool;

// Define tools fluently
$weatherTool = Tool::make('get_weather')
    ->description('Get current weather for a location')
    ->parameter('location', 'string', 'City and state', required: true)
    ->parameter('unit', 'string', 'celsius or fahrenheit', default: 'fahrenheit')
    ->handler(function (array $input) {
        return Weather::get($input['location'], $input['unit']);
    });

// Use in conversation
$response = Claude::conversation()
    ->tools([$weatherTool])
    ->user('What\'s the weather in Bryant, Arkansas?')
    ->maxSteps(5)  // Auto-execute tool calls
    ->send();
```

### 5. MCP Connector Integration (Differentiator)

```php
use Acme\Claude\Facades\Claude;
use Acme\Claude\MCP\McpServer;

// Connect to remote MCP servers
$response = Claude::conversation()
    ->mcp([
        McpServer::url('https://mcp.zapier.com/api')
            ->name('zapier')
            ->token(config('services.zapier.mcp_token'))
            ->allowTools(['gmail_send', 'slack_post']),
            
        McpServer::url('https://asana.mcp.anthropic.com/sse')
            ->name('asana')
            ->token($asanaToken),
    ])
    ->user('Create a task in Asana and notify the team on Slack')
    ->maxSteps(10)
    ->send();

// Pre-configured servers from config
$response = Claude::conversation()
    ->mcp(['zapier', 'asana'])  // Uses config/claude.php servers
    ->user('Send the quarterly report to the team')
    ->send();
```

### 6. Extended Thinking

```php
$response = Claude::conversation()
    ->model('claude-sonnet-4-5-20250929')
    ->extendedThinking(budgetTokens: 10000)
    ->user('Analyze this complex architectural decision...')
    ->send();

// Access thinking blocks
foreach ($response->content as $block) {
    if ($block->type === 'thinking') {
        Log::info('Claude reasoning:', ['thinking' => $block->thinking]);
    }
}
```

### 7. Streaming with Laravel Events

```php
use Acme\Claude\Facades\Claude;
use Acme\Claude\Events\StreamChunk;
use Acme\Claude\Events\StreamComplete;

// Event-based streaming
Claude::conversation()
    ->user('Write a detailed analysis...')
    ->stream(function (StreamChunk $chunk) {
        broadcast(new MessageChunk($chunk->text));
    });

// Or with Laravel's event system
Event::listen(StreamChunk::class, function ($event) {
    // Real-time processing
});
```

### 8. Prompt Caching

```php
use Acme\Claude\Facades\Claude;
use Acme\Claude\ValueObjects\CachedContent;

$response = Claude::conversation()
    ->system(
        CachedContent::make($longSystemPrompt)
            ->cache('ephemeral', ttl: '1h')
    )
    ->user('Analyze this contract clause...')
    ->send();

// Check cache usage
$response->usage->cache_creation_input_tokens;
$response->usage->cache_read_input_tokens;
```

### 9. Structured Outputs

```php
use Acme\Claude\Facades\Claude;

$response = Claude::conversation()
    ->user('Extract the key terms from this contract')
    ->schema([
        'type' => 'object',
        'properties' => [
            'parties' => ['type' => 'array', 'items' => ['type' => 'string']],
            'effective_date' => ['type' => 'string'],
            'term_length' => ['type' => 'string'],
            'key_obligations' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['parties', 'effective_date'],
    ])
    ->asStructured();

// Returns validated array
$parties = $response->parties;
```

### 10. Queue Integration

```php
use Acme\Claude\Jobs\ProcessConversation;

// Dispatch to queue
ProcessConversation::dispatch(
    conversation: Claude::conversation()
        ->system('Analyze this document...')
        ->user($documentContent),
    callback: ProcessedCallback::class
)->onQueue('ai');

// Job handles retries, rate limits, cost tracking
```

### 11. Testing

```php
use Acme\Claude\Facades\Claude;
use Acme\Claude\Testing\FakeResponse;

// Fake responses
Claude::fake([
    FakeResponse::make('Hello! I can help with that.'),
    FakeResponse::withToolUse('get_weather', ['location' => 'Bryant, AR']),
]);

// Your test code...
$response = Claude::conversation()
    ->user('What\'s the weather?')
    ->tools([$weatherTool])
    ->send();

// Assertions
Claude::assertSent(function ($request) {
    return str_contains($request->messages[0]['content'], 'weather');
});

Claude::assertToolCalled('get_weather');
Claude::assertConversationCount(1);
```

---

## MCP Connector Deep Dive

### Why This Matters

The MCP connector (`mcp-client-2025-11-20`) is transformative:

- **Before**: Build your own MCP client harness, manage connections, handle tool discovery
- **After**: Pass `mcp_servers` array to Messages API, Anthropic handles everything

This package would be the **first Laravel package** to wrap this capability elegantly.

### Implementation Architecture

```php
namespace Acme\Claude\MCP;

class McpServer
{
    public static function url(string $url): self
    {
        return new self(['type' => 'url', 'url' => $url]);
    }
    
    public function name(string $name): self
    {
        $this->config['name'] = $name;
        return $this;
    }
    
    public function token(string $token): self
    {
        $this->config['authorization_token'] = $token;
        return $this;
    }
    
    public function allowTools(array $tools): self
    {
        $this->toolConfig = [
            'enabled' => true,
            'allowed_tools' => $tools,
        ];
        return $this;
    }
    
    public function denyTools(array $tools): self
    {
        $this->toolConfig = [
            'enabled' => true,
            'denied_tools' => $tools,
        ];
        return $this;
    }
}
```

### Automatic Tool Execution Loop

```php
// Package handles the agentic loop automatically
$response = Claude::conversation()
    ->mcp([McpServer::url($url)->name('my-server')])
    ->user('Do the thing')
    ->maxSteps(10)  // Max tool call iterations
    ->send();

// Under the hood:
// 1. Send request with mcp_servers
// 2. Claude returns mcp_tool_use blocks
// 3. Anthropic executes tools on their infrastructure
// 4. Returns mcp_tool_result blocks
// 5. Loop continues until stop_reason or maxSteps
```

---

## Competitive Differentiation

| Feature | laravel-claude | mozex/anthropic-laravel | prism-php |
|---------|----------------|-------------------------|-----------|
| Official SDK wrapper | ✅ | ❌ (own client) | ❌ (own client) |
| MCP connector support | ✅ | ❌ | ❌ |
| Extended thinking | ✅ | ✅ | ✅ |
| Prompt caching | ✅ | ✅ | ✅ |
| Structured outputs | ✅ | ❌ | ✅ |
| Fluent conversation API | ✅ | ❌ | ✅ |
| Claude-specific optimizations | ✅ | ✅ | ❌ (generic) |
| Multi-provider support | ❌ | ❌ | ✅ |
| Laravel queue integration | ✅ | ❌ | ❌ |
| Testing utilities | ✅ | ✅ | ✅ |

### Unique Value Propositions

1. **First MCP connector wrapper** — Nobody else has this
2. **Official SDK foundation** — Always up-to-date with Anthropic's latest
3. **Claude-optimized** — Not a generic abstraction; every feature tuned for Claude
4. **Your MCP expertise** — You literally wrote production MCP servers (The Librarian, The Astronomer, The Ferryman)

---

## Implementation Roadmap

### Phase 1: Core (2 weeks)
- [ ] Service provider, facade, config
- [ ] Messages API wrapper
- [ ] Fluent conversation builder
- [ ] Streaming support
- [ ] Basic testing utilities

### Phase 2: Tools & MCP (2 weeks)
- [ ] Tool definition system
- [ ] Tool execution loop
- [ ] MCP connector integration
- [ ] Pre-configured MCP servers

### Phase 3: Advanced (2 weeks)
- [ ] Extended thinking support
- [ ] Prompt caching with Laravel cache integration
- [ ] Structured outputs with validation
- [ ] Queue job helpers
- [ ] Cost tracking utilities

### Phase 4: Polish (1 week)
- [ ] Documentation
- [ ] Example application
- [ ] Test coverage
- [ ] Laravel News announcement prep

---

## Technical Requirements

### Dependencies

```json
{
    "require": {
        "php": "^8.1",
        "anthropic-ai/sdk": "^0.4",
        "illuminate/support": "^10.0|^11.0|^12.0",
        "illuminate/contracts": "^10.0|^11.0|^12.0"
    },
    "require-dev": {
        "orchestra/testbench": "^8.0|^9.0|^10.0",
        "pestphp/pest": "^2.0|^3.0"
    }
}
```

### Minimum Versions
- PHP 8.1+
- Laravel 10+
- Anthropic SDK 0.4+

---

## Naming Options

| Option | Pros | Cons |
|--------|------|------|
| `laravel-claude` | Clear, memorable | Generic |
| `claude-laravel` | Matches `openai-php/laravel` pattern | — |
| `anthropic-laravel` | Official-sounding | Might conflict |
| `eloquent-claude` | Laravel-flavored | Might confuse with ORM |

**Recommendation**: `acme/laravel-claude` (or your vendor name)

---

## Success Metrics

- **Adoption**: 500+ GitHub stars in 6 months
- **Downloads**: 10k+ Packagist installs
- **Community**: Active issues/PRs, Laravel News feature
- **Differentiation**: Recognized as "the" Laravel Claude package

---

## Appendix: Research Sources

### Official Anthropic
- PHP SDK: github.com/anthropics/anthropic-sdk-php
- MCP Connector Docs: docs.claude.com/en/docs/agents-and-tools/mcp-connector
- API Reference: docs.anthropic.com

### Laravel AI Ecosystem
- Prism PHP: prismphp.com
- OpenAI Laravel: github.com/openai-php/laravel
- Laravel MCP (servers): github.com/laravel/mcp
- PHP MCP SDK: github.com/modelcontextprotocol/php-sdk

### MCP Client Libraries
- php-mcp/client: github.com/php-mcp/client
- swisnl/mcp-client: github.com/swisnl/mcp-client

---

*This specification represents a significant opportunity to establish the definitive Laravel + Claude integration, leveraging your unique MCP expertise and the timing of Anthropic's official PHP SDK release.*
