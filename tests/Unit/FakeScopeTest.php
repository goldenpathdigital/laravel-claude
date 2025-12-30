<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\ClaudeManager;
use GoldenPathDigital\Claude\Testing\FakeResponse;
use GoldenPathDigital\Claude\Testing\FakeScope;

afterEach(function () {
    ClaudeManager::clearFake();
});

test('fakeScoped returns FakeScope instance', function () {
    $scope = ClaudeManager::fakeScoped([
        FakeResponse::make('Scoped response'),
    ]);

    expect($scope)->toBeInstanceOf(FakeScope::class);
    expect(ClaudeManager::isFaking())->toBeTrue();

    $scope->dispose();
});

test('FakeScope dispose clears fake', function () {
    $scope = ClaudeManager::fakeScoped([]);

    expect(ClaudeManager::isFaking())->toBeTrue();

    $scope->dispose();

    expect(ClaudeManager::isFaking())->toBeFalse();
});

test('FakeScope provides access to fake instance', function () {
    $scope = ClaudeManager::fakeScoped([
        FakeResponse::make('Test'),
    ]);

    $fake = $scope->getFake();

    expect($fake)->not->toBeNull();

    $response = $fake->conversation()->user('Hello')->send();

    expect($response->content[0]->text)->toBe('Test');

    $scope->dispose();
});

test('multiple dispose calls are safe', function () {
    $scope = ClaudeManager::fakeScoped([]);

    $scope->dispose();
    $scope->dispose();
    $scope->dispose();

    expect(ClaudeManager::isFaking())->toBeFalse();
});

test('isValidModel returns true for any model in non-strict mode', function () {
    expect(ClaudeManager::isValidModel('any-model-name'))->toBeTrue();
    expect(ClaudeManager::isValidModel('custom-model'))->toBeTrue();
});

test('isValidModel validates known models in strict mode', function () {
    expect(ClaudeManager::isValidModel('claude-sonnet-4-5-20250929', strict: true))->toBeTrue();
    expect(ClaudeManager::isValidModel('claude-3-opus-20240229', strict: true))->toBeTrue();
    expect(ClaudeManager::isValidModel('unknown-model', strict: true))->toBeFalse();
});
