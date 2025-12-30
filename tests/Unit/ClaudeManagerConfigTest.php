<?php

declare(strict_types=1);

use Anthropic\RequestOptions;
use GoldenPathDigital\Claude\ClaudeManager;

test('config timeout is applied to client options', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'timeout' => 60,
    ]);

    $options = $manager->getClientOptions();

    expect($options)->toBeInstanceOf(RequestOptions::class);
    expect($options->timeout)->toBe(60.0);
});

test('config max_retries is applied to client options', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'max_retries' => 5,
    ]);

    $options = $manager->getClientOptions();

    expect($options->maxRetries)->toBe(5);
});

test('beta features add anthropic-beta header', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'beta_features' => [
            'mcp_connector' => true,
            'extended_thinking' => true,
            'prompt_caching' => false,
            'structured_outputs' => true,
        ],
    ]);

    $options = $manager->getClientOptions();

    expect($options->extraHeaders)->toHaveKey('anthropic-beta');
    expect($options->extraHeaders['anthropic-beta'])->toContain('mcp-client-2025-11-20');
    expect($options->extraHeaders['anthropic-beta'])->toContain('extended-thinking-2024-12-17');
    expect($options->extraHeaders['anthropic-beta'])->toContain('structured-outputs-2024-12-17');
    expect($options->extraHeaders['anthropic-beta'])->not->toContain('prompt-caching');
});

test('empty beta features does not add header', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'beta_features' => [],
    ]);

    $options = $manager->getClientOptions();

    expect($options->extraHeaders ?? [])->not->toHaveKey('anthropic-beta');
});

test('all disabled beta features does not add header', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'beta_features' => [
            'mcp_connector' => false,
            'extended_thinking' => false,
        ],
    ]);

    $options = $manager->getClientOptions();

    expect($options->extraHeaders ?? [])->not->toHaveKey('anthropic-beta');
});

test('config values are accessible via config method', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'default_model' => 'claude-sonnet-4-5-20250929',
        'custom_value' => 'test',
    ]);

    expect($manager->config('default_model'))->toBe('claude-sonnet-4-5-20250929');
    expect($manager->config('custom_value'))->toBe('test');
    expect($manager->config('missing', 'default'))->toBe('default');
});

test('numeric string timeout is converted to float', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'timeout' => '120',
    ]);

    $options = $manager->getClientOptions();

    expect($options->timeout)->toBe(120.0);
});

test('numeric string max_retries is converted to int', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'max_retries' => '3',
    ]);

    $options = $manager->getClientOptions();

    expect($options->maxRetries)->toBe(3);
});
