<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\ClaudeManager;

test('isKnownModel returns true for known models', function () {
    expect(ClaudeManager::isKnownModel('claude-sonnet-4-5-20250929'))->toBeTrue();
    expect(ClaudeManager::isKnownModel('claude-3-opus-20240229'))->toBeTrue();
    expect(ClaudeManager::isKnownModel('claude-3-5-sonnet-20241022'))->toBeTrue();
});

test('isKnownModel returns true for pattern-matched models', function () {
    expect(ClaudeManager::isKnownModel('claude-3-opus-20250101'))->toBeTrue();
    expect(ClaudeManager::isKnownModel('claude-sonnet-4-20251231'))->toBeTrue();
});

test('isKnownModel returns false for unknown models', function () {
    expect(ClaudeManager::isKnownModel('gpt-4'))->toBeFalse();
    expect(ClaudeManager::isKnownModel('claude-unknown'))->toBeFalse();
    expect(ClaudeManager::isKnownModel('random-model'))->toBeFalse();
});
