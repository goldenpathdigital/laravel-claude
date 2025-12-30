<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\ClaudeManager;
use GoldenPathDigital\Claude\Events\StreamChunk;
use GoldenPathDigital\Claude\Events\StreamComplete;
use GoldenPathDigital\Claude\Testing\FakeResponse;

afterEach(function () {
    ClaudeManager::clearFake();
});

test('stream returns StreamComplete with collected text', function () {
    $fake = ClaudeManager::fake([
        FakeResponse::make('Hello, this is a streaming response!'),
    ]);

    $chunks = [];
    $complete = $fake->conversation()
        ->user('Say hello')
        ->stream(function (StreamChunk $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

    expect($complete)->toBeInstanceOf(StreamComplete::class);
    expect($complete->fullText)->toBe('Hello, this is a streaming response!');
    expect($complete->stopReason)->toBe('end_turn');
    expect($chunks)->not->toBeEmpty();
});

test('stream chunks are emitted with correct structure', function () {
    $fake = ClaudeManager::fake([
        FakeResponse::make('Test response'),
    ]);

    $chunks = [];
    $fake->conversation()
        ->user('Test')
        ->stream(function (StreamChunk $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

    foreach ($chunks as $index => $chunk) {
        expect($chunk)->toBeInstanceOf(StreamChunk::class);
        expect($chunk->text)->toBeString();
        expect($chunk->index)->toBe($index);
        expect($chunk->type)->toBe('text_delta');
    }
});

test('stream complete has usage information', function () {
    $fake = ClaudeManager::fake([
        FakeResponse::make('Response')
            ->usage(100, 50),
    ]);

    $complete = $fake->conversation()
        ->user('Test')
        ->stream(function () {});

    $usage = $complete->usage();
    expect($usage['input_tokens'])->toBe(100);
    expect($usage['output_tokens'])->toBe(50);
});

test('streaming records requests', function () {
    $fake = ClaudeManager::fake([
        FakeResponse::make('Response'),
    ]);

    $fake->conversation()
        ->system('Be helpful')
        ->user('Hello')
        ->stream(function () {});

    expect($fake->recorded())->toHaveCount(1);
    expect($fake->recorded()[0]['system'])->toBe('Be helpful');
});
