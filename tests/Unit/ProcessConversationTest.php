<?php

declare(strict_types=1);

use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\Usage;
use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Contracts\ConversationCallback;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use GoldenPathDigital\Claude\Conversation\PayloadBuilder;
use GoldenPathDigital\Claude\Jobs\ProcessConversation;
use GoldenPathDigital\Claude\MCP\McpServer;
use GoldenPathDigital\Claude\Tools\Tool;
use GoldenPathDigital\Claude\ValueObjects\CachedContent;

beforeEach(function () {
    $this->mockClient = Mockery::mock(ClaudeClientInterface::class);
    $this->mockClient->shouldReceive('config')
        ->with('default_model')
        ->andReturn('claude-sonnet-4-5-20250929');
});

function createMockMessage(string $text = 'Test response'): Message
{
    return new Message(
        id: 'msg_123',
        type: 'message',
        role: 'assistant',
        content: [
            new TextBlock(type: 'text', text: $text),
        ],
        model: 'claude-sonnet-4-5-20250929',
        stop_reason: 'end_turn',
        stop_sequence: null,
        usage: new Usage(
            input_tokens: 10,
            output_tokens: 20,
            cache_creation_input_tokens: null,
            cache_read_input_tokens: null,
        ),
    );
}

test('serializes conversation builder config', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->model('claude-sonnet-4-5-20250929')
        ->system('You are helpful')
        ->user('Hello')
        ->maxTokens(2048)
        ->temperature(0.7);

    $job = new ProcessConversation($conversation, TestCallback::class);

    $reflection = new ReflectionClass($job);
    $configProperty = $reflection->getProperty('config');
    $configProperty->setAccessible(true);
    $config = $configProperty->getValue($job);

    expect($config['model'])->toBe('claude-sonnet-4-5-20250929');
    expect($config['system'])->toBe('You are helpful');
    expect($config['messages'])->toHaveCount(1);
    expect($config['messages'][0]['content'])->toBe('Hello');
    expect($config['max_tokens'])->toBe(2048);
    expect($config['temperature'])->toBe(0.7);
});

test('serializes context array', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation->user('Test');

    $context = ['user_id' => 123, 'document_id' => 'abc'];
    $job = new ProcessConversation($conversation, TestCallback::class, $context);

    $reflection = new ReflectionClass($job);
    $contextProperty = $reflection->getProperty('context');
    $contextProperty->setAccessible(true);

    expect($contextProperty->getValue($job))->toBe($context);
});

test('builds basic payload correctly', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->system('You are helpful')
        ->user('Hello')
        ->maxTokens(1024);

    $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $payloadBuilder->build();

    expect($payload['model'])->toBe('claude-sonnet-4-5-20250929');
    expect($payload['max_tokens'])->toBe(1024);
    expect($payload['messages'])->toHaveCount(1);
    expect($payload['system'])->toBe('You are helpful');
});

test('builds payload with extended thinking', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Complex question')
        ->extendedThinking(budgetTokens: 5000);

    $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $payloadBuilder->build();

    expect($payload['thinking'])->toBe([
        'type' => 'enabled',
        'budget_tokens' => 5000,
    ]);
});

test('builds payload with cached content', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $cached = CachedContent::make('Long system prompt')->cache('ephemeral');
    $conversation
        ->system($cached)
        ->user('Question');

    $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $payloadBuilder->build();

    expect($payload['system'])->toBeArray();
    expect($payload['system'][0]['type'])->toBe('text');
    expect($payload['system'][0]['cache_control'])->toBe(['type' => 'ephemeral']);
});

test('builds payload with tools', function () {
    $tool = Tool::make('get_data')
        ->description('Get data')
        ->parameter('id', 'string', 'The ID', required: true);

    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Get data for id 123')
        ->tools([$tool]);

    $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $payloadBuilder->build();

    expect($payload['tools'])->toHaveCount(1);
    expect($payload['tools'][0]['name'])->toBe('get_data');
});

test('builds payload with json schema', function () {
    $schema = [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
        ],
    ];

    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Extract name')
        ->schema($schema, 'extract_data');

    $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $payloadBuilder->build();

    expect($payload['tools'])->toContain([
        'name' => 'extract_data',
        'description' => 'Respond with structured data matching the provided schema',
        'input_schema' => $schema,
    ]);
    expect($payload['tool_choice'])->toBe([
        'type' => 'tool',
        'name' => 'extract_data',
    ]);
});

test('builds payload with mcp servers', function () {
    $server = McpServer::url('https://mcp.example.com/api')
        ->name('example')
        ->token('secret-token');

    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Use the tool')
        ->mcp([$server]);

    $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $payloadBuilder->build();

    expect($payload['mcp_servers'])->toHaveCount(1);
    expect($payload['mcp_servers'][0]['url'])->toBe('https://mcp.example.com/api');
});

test('builds payload with stop sequences', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Write until you see END')
        ->stopSequences(['END', '---']);

    $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $payloadBuilder->build();

    expect($payload['stop_sequences'])->toBe(['END', '---']);
});

test('builds payload with top_k', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Test')
        ->topK(40);

    $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $payloadBuilder->build();

    expect($payload['top_k'])->toBe(40);
});

test('builds payload with top_p', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Test')
        ->topP(0.9);

    $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $payloadBuilder->build();

    expect($payload['top_p'])->toBe(0.9);
});

test('builds payload with metadata', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Test')
        ->metadata(['user_id' => 'user_123']);

    $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $payloadBuilder->build();

    expect($payload['metadata'])->toBe(['user_id' => 'user_123']);
});

test('builds payload with service tier', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Test')
        ->serviceTier('auto');

    $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $payloadBuilder->build();

    expect($payload['service_tier'])->toBe('auto');
});

test('has default tries and backoff', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation->user('Test');

    $job = new ProcessConversation($conversation, TestCallback::class);

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe(10);
});

test('stores callback class name', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation->user('Test');

    $job = new ProcessConversation($conversation, TestCallback::class);

    $reflection = new ReflectionClass($job);
    $callbackProperty = $reflection->getProperty('callbackClass');
    $callbackProperty->setAccessible(true);

    expect($callbackProperty->getValue($job))->toBe(TestCallback::class);
});

class TestCallback implements ConversationCallback
{
    public static ?Message $lastResponse = null;

    public static ?Throwable $lastException = null;

    public static ?array $lastContext = null;

    public function onSuccess(Message $response, array $context = []): void
    {
        self::$lastResponse = $response;
        self::$lastContext = $context;
    }

    public function onFailure(Throwable $exception, array $context = []): void
    {
        self::$lastException = $exception;
        self::$lastContext = $context;
    }
}
