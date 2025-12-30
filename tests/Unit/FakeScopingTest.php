<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\ClaudeManager;
use GoldenPathDigital\Claude\Testing\FakeResponse;
use GoldenPathDigital\Claude\Testing\PendingClaudeFake;

afterEach(function () {
    ClaudeManager::clearFake();
});

test('fake creates PendingClaudeFake instance', function () {
    $fake = ClaudeManager::fake();

    expect($fake)->toBeInstanceOf(PendingClaudeFake::class);
    expect(ClaudeManager::isFaking())->toBeTrue();
    expect(ClaudeManager::getFake())->toBe($fake);
});

test('clearFake removes static fake reference', function () {
    ClaudeManager::fake();
    expect(ClaudeManager::isFaking())->toBeTrue();

    ClaudeManager::clearFake();
    expect(ClaudeManager::isFaking())->toBeFalse();
    expect(ClaudeManager::getFake())->toBeNull();
});

test('fake with responses queues them for consumption', function () {
    $fake = ClaudeManager::fake([
        FakeResponse::make('First response'),
        FakeResponse::make('Second response'),
    ]);

    $response1 = $fake->messages()->create(['messages' => []]);
    $response2 = $fake->messages()->create(['messages' => []]);

    expect($response1->content[0]->text)->toBe('First response');
    expect($response2->content[0]->text)->toBe('Second response');
});

test('conversation routes through fake when active', function () {
    ClaudeManager::fake([
        FakeResponse::make('Fake response'),
    ]);

    $manager = new ClaudeManager(['api_key' => 'test']);
    $conversation = $manager->conversation();

    expect($conversation)->toBeInstanceOf(\GoldenPathDigital\Claude\Conversation\ConversationBuilder::class);
});

test('multiple fakes replace previous fake', function () {
    $fake1 = ClaudeManager::fake([FakeResponse::make('First')]);
    $fake2 = ClaudeManager::fake([FakeResponse::make('Second')]);

    expect(ClaudeManager::getFake())->toBe($fake2);
    expect(ClaudeManager::getFake())->not->toBe($fake1);
});

test('fake binds to container when available', function () {
    $fake = ClaudeManager::fake();

    $resolved = app(ClaudeManager::class);
    expect($resolved)->toBe($fake);
});

test('clearFake removes container binding', function () {
    ClaudeManager::fake();
    expect(app()->bound(ClaudeManager::class))->toBeTrue();

    ClaudeManager::clearFake();

    app()->forgetInstance(ClaudeManager::class);
    $fresh = app(ClaudeManager::class);
    expect($fresh)->toBeInstanceOf(ClaudeManager::class);
    expect($fresh)->not->toBeInstanceOf(PendingClaudeFake::class);
});
