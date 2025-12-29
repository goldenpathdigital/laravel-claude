<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\ValueObjects\CachedContent;

test('creates cached content with text', function () {
    $cached = CachedContent::make('You are a helpful assistant.');

    expect($cached->getContent())->toBe('You are a helpful assistant.');
    expect($cached->getCacheType())->toBe('ephemeral');
});

test('sets cache type', function () {
    $cached = CachedContent::make('System prompt')
        ->cache('ephemeral');

    expect($cached->getCacheType())->toBe('ephemeral');
});

test('uses ephemeral helper', function () {
    $cached = CachedContent::make('System prompt')
        ->ephemeral();

    expect($cached->getCacheType())->toBe('ephemeral');
});

test('converts to array with cache control', function () {
    $cached = CachedContent::make('You are a code reviewer.')
        ->cache('ephemeral');

    $array = $cached->toArray();

    expect($array)->toBe([
        'type' => 'text',
        'text' => 'You are a code reviewer.',
        'cache_control' => [
            'type' => 'ephemeral',
        ],
    ]);
});

test('fluent interface returns self', function () {
    $cached = CachedContent::make('Test');

    expect($cached->cache('ephemeral'))->toBe($cached);
    expect($cached->ephemeral())->toBe($cached);
});
