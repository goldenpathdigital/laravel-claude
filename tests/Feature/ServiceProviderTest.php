<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\ClaudeManager;
use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;

test('service provider registers claude manager singleton', function () {
    $instance1 = app(ClaudeManager::class);
    $instance2 = app(ClaudeManager::class);

    expect($instance1)->toBeInstanceOf(ClaudeManager::class);
    expect($instance1)->toBe($instance2);
});

test('service provider binds interface to implementation', function () {
    $instance = app(ClaudeClientInterface::class);

    expect($instance)->toBeInstanceOf(ClaudeManager::class);
});

test('claude alias resolves to manager', function () {
    $instance = app('claude');

    expect($instance)->toBeInstanceOf(ClaudeManager::class);
});

test('config is accessible through manager', function () {
    $manager = app(ClaudeManager::class);

    expect($manager->config('default_model'))->toBe('claude-sonnet-4-5-20250929');
});
