# Laravel Claude

[![Latest Version on Packagist](https://img.shields.io/packagist/v/goldenpathdigital/laravel-claude.svg?style=flat-square)](https://packagist.org/packages/goldenpathdigital/laravel-claude)
[![Total Downloads](https://img.shields.io/packagist/dt/goldenpathdigital/laravel-claude.svg?style=flat-square)](https://packagist.org/packages/goldenpathdigital/laravel-claude)
[![License](https://img.shields.io/packagist/l/goldenpathdigital/laravel-claude.svg?style=flat-square)](https://packagist.org/packages/goldenpathdigital/laravel-claude)

A Laravel wrapper for the official [Anthropic PHP SDK](https://github.com/anthropics/anthropic-sdk-php) with first-class MCP connector support.

## Features

- **Official SDK** — Wraps `anthropic-ai/sdk`, not a custom HTTP implementation
- **Laravel Native** — Facades, config, service provider, auto-discovery
- **Fluent API** — Chainable conversation builder
- **Tool System** — Define tools with fluent builder and automatic execution loop
- **MCP Connector** — First Laravel package with MCP client support
- **Streaming** — Real-time streaming with Laravel events
- **Extended Thinking** — Access Claude's reasoning process with budget tokens
- **Prompt Caching** — Reduce costs with cached system prompts
- **Structured Outputs** — JSON schema validation for responses
- **Queue Integration** — Process conversations in background jobs with retry handling
- **Testing Utilities** — `Claude::fake()` with assertion helpers

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

### Streaming

Stream responses in real-time with callback support:

```php
use GoldenPathDigital\Claude\Facades\Claude;

Claude::conversation()
    ->system('You are a helpful assistant.')
    ->user('Write a short poem about Laravel.')
    ->stream(function (string $text) {
        echo $text; // Output each chunk as it arrives
    });
```

Or listen for Laravel events:

```php
// In your EventServiceProvider or listener
use GoldenPathDigital\Claude\Events\StreamChunk;
use GoldenPathDigital\Claude\Events\StreamComplete;

Event::listen(StreamChunk::class, function (StreamChunk $event) {
    broadcast(new NewChunk($event->text)); // Real-time to frontend
});

Event::listen(StreamComplete::class, function (StreamComplete $event) {
    logger()->info('Stream complete', [
        'input_tokens' => $event->usage['input_tokens'],
        'output_tokens' => $event->usage['output_tokens'],
    ]);
});
```

### Tools

Define tools with a fluent builder and let Claude execute them automatically:

```php
use GoldenPathDigital\Claude\Facades\Claude;
use GoldenPathDigital\Claude\Tools\Tool;

$weatherTool = Tool::make('get_weather')
    ->description('Get the current weather for a location')
    ->parameter('location', 'string', 'City name', required: true)
    ->parameter('units', 'string', 'Temperature units', enum: ['celsius', 'fahrenheit'])
    ->handler(function (array $input) {
        // Call your weather API here
        return ['temperature' => 72, 'condition' => 'sunny'];
    });

$response = Claude::conversation()
    ->system('You are a helpful assistant with access to weather data.')
    ->user('What is the weather in Paris?')
    ->tools([$weatherTool])
    ->maxSteps(5) // Maximum tool execution iterations
    ->send();

echo $response->content[0]->text;
// "The current weather in Paris is 72 degrees and sunny."
```

### MCP Connector

Connect to remote MCP servers via Anthropic's connector API:

```php
use GoldenPathDigital\Claude\Facades\Claude;
use GoldenPathDigital\Claude\MCP\McpServer;

// Define MCP server inline
$zapier = McpServer::url('https://mcp.zapier.com/api/mcp/s/xxx')
    ->name('zapier')
    ->token(env('ZAPIER_MCP_TOKEN'))
    ->allowTools(['gmail_send', 'slack_post']); // Optional: restrict tools

$response = Claude::conversation()
    ->system('You are an assistant that can send emails and Slack messages.')
    ->user('Send a Slack message to #general saying hello')
    ->mcp([$zapier])
    ->send();
```

Or use pre-configured servers from config:

```php
// config/claude.php
'mcp_servers' => [
    'zapier' => [
        'url' => env('ZAPIER_MCP_URL'),
        'token' => env('ZAPIER_MCP_TOKEN'),
        'allowed_tools' => ['gmail_send', 'slack_post'],
    ],
],

// Usage - reference by config key
$response = Claude::conversation()
    ->mcp(['zapier']) // Loads from config
    ->user('Send an email to john@example.com')
    ->send();
```

### Extended Thinking

Enable Claude's reasoning process for complex problems:

```php
use GoldenPathDigital\Claude\Facades\Claude;

$response = Claude::conversation()
    ->model('claude-sonnet-4-5-20250929')
    ->extendedThinking(budgetTokens: 10000)
    ->user('Analyze the pros and cons of microservices vs monolith architecture.')
    ->send();

// Access thinking blocks in response
foreach ($response->content as $block) {
    if ($block->type === 'thinking') {
        logger()->info('Claude reasoning:', ['thinking' => $block->thinking]);
    }
    if ($block->type === 'text') {
        echo $block->text;
    }
}
```

### Prompt Caching

Reduce costs by caching large system prompts:

```php
use GoldenPathDigital\Claude\Facades\Claude;
use GoldenPathDigital\Claude\ValueObjects\CachedContent;

// Cache a long system prompt
$systemPrompt = CachedContent::make($longDocumentation)
    ->cache('ephemeral');

$response = Claude::conversation()
    ->system($systemPrompt)
    ->user('Summarize the key points.')
    ->send();

// Check cache usage in response
// $response->usage->cache_creation_input_tokens
// $response->usage->cache_read_input_tokens
```

### Structured Outputs

Get responses validated against a JSON schema:

```php
use GoldenPathDigital\Claude\Facades\Claude;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;

$schema = [
    'type' => 'object',
    'properties' => [
        'parties' => ['type' => 'array', 'items' => ['type' => 'string']],
        'effective_date' => ['type' => 'string'],
        'term_length' => ['type' => 'string'],
        'key_obligations' => ['type' => 'array', 'items' => ['type' => 'string']],
    ],
    'required' => ['parties', 'effective_date'],
];

$response = Claude::conversation()
    ->user('Extract the key terms from this contract: ...')
    ->schema($schema, 'contract_terms')
    ->send();

// Extract structured data from the tool_use response
$data = ConversationBuilder::extractStructuredOutput($response);

echo $data['parties'][0]; // "Acme Corp"
echo $data['effective_date']; // "2025-01-01"
```

## Testing

Use `Claude::fake()` to mock responses in your tests:

```php
use GoldenPathDigital\Claude\Facades\Claude;
use GoldenPathDigital\Claude\Testing\FakeResponse;

public function test_chatbot_responds()
{
    Claude::fake([
        FakeResponse::make('Hello! How can I help you today?'),
    ]);

    $response = Claude::conversation()
        ->user('Hi there!')
        ->send();

    $this->assertEquals('Hello! How can I help you today?', $response->content[0]->text);

    // Assert the request was sent
    Claude::assertSent(function (array $request) {
        return $request['messages'][0]['content'] === 'Hi there!';
    });
}
```

### Available Assertions

```php
// Assert any request was sent
Claude::assertSent();

// Assert with callback
Claude::assertSent(function (array $request) {
    return str_contains($request['messages'][0]['content'], 'hello');
});

// Assert nothing was sent
Claude::assertNothingSent();

// Assert specific count
Claude::assertSentCount(3);
```

### Faking Tool Use Responses

```php
Claude::fake([
    FakeResponse::withToolUse('get_weather', ['location' => 'Paris']),
    FakeResponse::make('The weather in Paris is sunny and 72 degrees.'),
]);
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
        'zapier' => [
            'url' => env('ZAPIER_MCP_URL'),
            'token' => env('ZAPIER_MCP_TOKEN'),
        ],
    ],
];
```

### Queue Integration

Process conversations in background jobs with automatic retry handling:

```php
use GoldenPathDigital\Claude\Facades\Claude;
use GoldenPathDigital\Claude\Jobs\ProcessConversation;
use GoldenPathDigital\Claude\Contracts\ConversationCallback;
use Anthropic\Messages\Message;
use Throwable;

// Create a callback to handle the result
class DocumentAnalysisCallback implements ConversationCallback
{
    public function onSuccess(Message $response, array $context = []): void
    {
        $document = Document::find($context['document_id']);
        $document->update([
            'summary' => $response->content[0]->text,
            'analyzed_at' => now(),
        ]);
    }

    public function onFailure(Throwable $exception, array $context = []): void
    {
        Log::error('Document analysis failed', [
            'document_id' => $context['document_id'],
            'error' => $exception->getMessage(),
        ]);
    }
}

// Dispatch the conversation to the queue
ProcessConversation::dispatch(
    conversation: Claude::conversation()
        ->system('You are a document analyst. Summarize the key points.')
        ->user($documentContent),
    callbackClass: DocumentAnalysisCallback::class,
    context: ['document_id' => $document->id]
)->onQueue('ai');
```

The job includes:
- **Automatic retries**: 3 attempts with 10 second backoff
- **Callback pattern**: Handle success/failure in dedicated classes
- **Context passing**: Pass arbitrary data to the callback
- **Full feature support**: MCP servers, extended thinking, caching, structured outputs

**Note**: Tools with custom handlers (closures) cannot be serialized for queue jobs. Use MCP servers or basic conversations for queued processing.

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
