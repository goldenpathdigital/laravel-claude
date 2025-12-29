<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use GoldenPathDigital\Claude\ValueObjects\CachedContent;

beforeEach(function () {
    $this->mockClient = Mockery::mock(ClaudeClientInterface::class);
    $this->mockClient->shouldReceive('config')
        ->with('default_model')
        ->andReturn('claude-sonnet-4-5-20250929');
});

test('extended thinking adds thinking config to payload', function () {
    $builder = new ConversationBuilder($this->mockClient);

    $builder
        ->user('Analyze this complex problem')
        ->extendedThinking(budgetTokens: 10000);

    $array = $builder->toArray();

    expect($array['thinking_budget'])->toBe(10000);
});

test('system accepts CachedContent', function () {
    $builder = new ConversationBuilder($this->mockClient);

    $cached = CachedContent::make('You are a helpful assistant.')
        ->cache('ephemeral');

    $builder->system($cached);

    $array = $builder->toArray();

    expect($array['system'])->toBe([
        'type' => 'text',
        'text' => 'You are a helpful assistant.',
        'cache_control' => [
            'type' => 'ephemeral',
        ],
    ]);
});

test('system accepts plain string', function () {
    $builder = new ConversationBuilder($this->mockClient);

    $builder->system('You are a helpful assistant.');

    $array = $builder->toArray();

    expect($array['system'])->toBe('You are a helpful assistant.');
});

test('schema adds json schema to payload', function () {
    $builder = new ConversationBuilder($this->mockClient);

    $schema = [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
        ],
        'required' => ['name'],
    ];

    $builder
        ->user('Extract information')
        ->schema($schema, 'person_info');

    $array = $builder->toArray();

    expect($array['json_schema'])->toBe($schema);
});

test('schema with default name', function () {
    $builder = new ConversationBuilder($this->mockClient);

    $schema = [
        'type' => 'object',
        'properties' => [
            'items' => ['type' => 'array'],
        ],
    ];

    $builder->schema($schema);

    $array = $builder->toArray();

    expect($array['json_schema'])->toBe($schema);
});

test('all phase 3 features can be combined', function () {
    $builder = new ConversationBuilder($this->mockClient);

    $cached = CachedContent::make('You are a document analyzer.')
        ->ephemeral();

    $schema = [
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'key_points' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ];

    $builder
        ->system($cached)
        ->user('Analyze this document')
        ->extendedThinking(5000)
        ->schema($schema, 'document_analysis');

    $array = $builder->toArray();

    expect($array['system'])->toBeArray();
    expect($array['system']['cache_control']['type'])->toBe('ephemeral');
    expect($array['thinking_budget'])->toBe(5000);
    expect($array['json_schema'])->toBe($schema);
});
