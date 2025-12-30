<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\ClaudeManager;
use GoldenPathDigital\Claude\Exceptions\ConfigurationException;

test('throws ConfigurationException when no auth configured', function () {
    new ClaudeManager([]);
})->throws(ConfigurationException::class, 'Either ANTHROPIC_API_KEY or ANTHROPIC_AUTH_TOKEN must be configured');

test('accepts api_key authentication', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
    ]);

    expect($manager)->toBeInstanceOf(ClaudeManager::class);
});

test('accepts auth_token authentication', function () {
    $manager = new ClaudeManager([
        'auth_token' => 'test-token',
    ]);

    expect($manager)->toBeInstanceOf(ClaudeManager::class);
});

test('accepts both api_key and auth_token', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'auth_token' => 'test-token',
    ]);

    expect($manager)->toBeInstanceOf(ClaudeManager::class);
});

test('empty string api_key is rejected', function () {
    new ClaudeManager([
        'api_key' => '',
    ]);
})->throws(ConfigurationException::class);

test('null api_key with empty auth_token is rejected', function () {
    new ClaudeManager([
        'api_key' => null,
        'auth_token' => '',
    ]);
})->throws(ConfigurationException::class);
