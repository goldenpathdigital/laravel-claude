<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\Testing\FakeMessagesService;
use GoldenPathDigital\Claude\Testing\FakeResponse;

test('returns queued responses in order', function () {
    $service = new FakeMessagesService([
        FakeResponse::make('First response'),
        FakeResponse::make('Second response'),
    ]);

    $first = $service->create(['model' => 'test', 'messages' => []]);
    $second = $service->create(['model' => 'test', 'messages' => []]);

    expect($first->content[0]->text)->toBe('First response');
    expect($second->content[0]->text)->toBe('Second response');
});

test('records all requests', function () {
    $service = new FakeMessagesService([
        FakeResponse::make('Response'),
    ]);

    $service->create(['model' => 'claude-sonnet', 'messages' => [['role' => 'user', 'content' => 'Hello']]]);

    $recorded = $service->recorded();
    expect($recorded)->toHaveCount(1);
    expect($recorded[0]['model'])->toBe('claude-sonnet');
});

test('asserts sent with callback', function () {
    $service = new FakeMessagesService([
        FakeResponse::make('Response'),
    ]);

    $service->create(['model' => 'claude-sonnet', 'messages' => [['role' => 'user', 'content' => 'What is the weather?']]]);

    expect($service->assertSent(fn ($r) => str_contains($r['messages'][0]['content'], 'weather')))->toBeTrue();
    expect($service->assertSent(fn ($r) => str_contains($r['messages'][0]['content'], 'pizza')))->toBeFalse();
});
