<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use GoldenPathDigital\Claude\Conversation\PayloadBuilder;
use GoldenPathDigital\Claude\MCP\McpServer;
use GoldenPathDigital\Claude\Tools\Tool;
use GoldenPathDigital\Claude\ValueObjects\CachedContent;

beforeEach(function () {
    $this->mockClient = Mockery::mock(ClaudeClientInterface::class);
    $this->mockClient->shouldReceive('config')
        ->with('default_model')
        ->andReturn('claude-sonnet-4-5-20250929');
});

test('builds base payload with required fields', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation->user('Hello')->maxTokens(1024);

    $builder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $builder->build();

    expect($payload)->toHaveKey('model');
    expect($payload)->toHaveKey('max_tokens');
    expect($payload)->toHaveKey('messages');
    expect($payload['model'])->toBe('claude-sonnet-4-5-20250929');
    expect($payload['max_tokens'])->toBe(1024);
});

test('adds system prompt as string', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->system('You are helpful')
        ->user('Hello');

    $builder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $builder->build();

    expect($payload['system'])->toBe('You are helpful');
});

test('adds system prompt as cached content', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $cached = CachedContent::make('Long documentation')->cache('ephemeral');
    $conversation
        ->system($cached)
        ->user('Question');

    $builder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $builder->build();

    expect($payload['system'])->toBeArray();
    expect($payload['system'][0]['type'])->toBe('text');
    expect($payload['system'][0]['cache_control'])->toBe(['type' => 'ephemeral']);
});

test('adds sampling parameters when set', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Test')
        ->temperature(0.7)
        ->topK(40)
        ->topP(0.9)
        ->stopSequences(['END', '---']);

    $builder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $builder->build();

    expect($payload['temperature'])->toBe(0.7);
    expect($payload['top_k'])->toBe(40);
    expect($payload['top_p'])->toBe(0.9);
    expect($payload['stop_sequences'])->toBe(['END', '---']);
});

test('excludes optional parameters when not set', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation->user('Test');

    $builder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $builder->build();

    expect($payload)->not->toHaveKey('temperature');
    expect($payload)->not->toHaveKey('top_k');
    expect($payload)->not->toHaveKey('top_p');
    expect($payload)->not->toHaveKey('stop_sequences');
    expect($payload)->not->toHaveKey('system');
    expect($payload)->not->toHaveKey('tools');
});

test('adds extended thinking configuration', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Complex problem')
        ->extendedThinking(budgetTokens: 5000);

    $builder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $builder->build();

    expect($payload['thinking'])->toBe([
        'type' => 'enabled',
        'budget_tokens' => 5000,
    ]);
});

test('adds tools to payload', function () {
    $tool = Tool::make('search')
        ->description('Search the web')
        ->parameter('query', 'string', 'Search query', required: true);

    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Search for something')
        ->tools([$tool]);

    $builder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $builder->build();

    expect($payload['tools'])->toHaveCount(1);
    expect($payload['tools'][0]['name'])->toBe('search');
});

test('adds json schema as tool', function () {
    $schema = [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
        ],
    ];

    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Extract data')
        ->schema($schema, 'extract_person');

    $builder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $builder->build();

    expect($payload['tools'])->toContain([
        'name' => 'extract_person',
        'description' => 'Respond with structured data matching the provided schema',
        'input_schema' => $schema,
    ]);
    expect($payload['tool_choice'])->toBe([
        'type' => 'tool',
        'name' => 'extract_person',
    ]);
});

test('adds metadata and service tier', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Test')
        ->metadata(['user_id' => 'user_123'])
        ->serviceTier('auto');

    $builder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $builder->build();

    expect($payload['metadata'])->toBe(['user_id' => 'user_123']);
    expect($payload['service_tier'])->toBe('auto');
});

test('adds mcp servers and toolsets', function () {
    $server = McpServer::url('https://mcp.example.com/api')
        ->name('example')
        ->token('secret');

    $conversation = new ConversationBuilder($this->mockClient);
    $conversation
        ->user('Use tool')
        ->mcp([$server]);

    $builder = PayloadBuilder::fromConfig($conversation->toArray());
    $payload = $builder->build();

    expect($payload['mcp_servers'])->toHaveCount(1);
    expect($payload['mcp_servers'][0]['url'])->toBe('https://mcp.example.com/api');
    expect($payload['tools'])->toHaveCount(1);
    expect($payload['tools'][0]['type'])->toBe('mcp_toolset');
});

test('setMessages updates messages in config', function () {
    $conversation = new ConversationBuilder($this->mockClient);
    $conversation->user('Hello');

    $builder = PayloadBuilder::fromConfig($conversation->toArray());

    $newMessages = [
        ['role' => 'user', 'content' => 'Updated message'],
        ['role' => 'assistant', 'content' => 'Response'],
    ];

    $builder->setMessages($newMessages);
    $payload = $builder->build();

    expect($payload['messages'])->toBe($newMessages);
});
