<?php

declare(strict_types=1);

use Anthropic\Beta\Messages\BetaMessageTokensCount;
use GoldenPathDigital\Claude\ClaudeManager;
use GoldenPathDigital\Claude\Testing\PendingClaudeFake;
use GoldenPathDigital\Claude\ValueObjects\TokenCost;

test('token cost calculates input and output costs correctly', function () {
    $pricing = ['input' => 3.00, 'output' => 15.00];

    $cost = TokenCost::calculate(
        inputTokens: 1_000_000,
        outputTokens: 1_000_000,
        pricing: $pricing,
        model: 'claude-sonnet-4-5-20250929'
    );

    expect($cost->inputTokens)->toBe(1_000_000);
    expect($cost->outputTokens)->toBe(1_000_000);
    expect($cost->inputCost)->toBe(3.00);
    expect($cost->outputCost)->toBe(15.00);
    expect($cost->total())->toBe(18.00);
    expect($cost->model)->toBe('claude-sonnet-4-5-20250929');
});

test('token cost calculates fractional costs correctly', function () {
    $pricing = ['input' => 3.00, 'output' => 15.00];

    $cost = TokenCost::calculate(
        inputTokens: 1000,
        outputTokens: 500,
        pricing: $pricing,
        model: 'claude-sonnet'
    );

    expect($cost->inputCost)->toBe(0.003);
    expect($cost->outputCost)->toBe(0.0075);
    expect(round($cost->total(), 6))->toBe(0.0105);
});

test('token cost for input only', function () {
    $pricing = ['input' => 3.00, 'output' => 15.00];

    $cost = TokenCost::forInput(
        inputTokens: 10000,
        pricing: $pricing,
        model: 'claude-sonnet'
    );

    expect($cost->inputTokens)->toBe(10000);
    expect($cost->outputTokens)->toBe(0);
    expect($cost->inputCost)->toBe(0.03);
    expect($cost->outputCost)->toBe(0.0);
});

test('token cost total tokens', function () {
    $pricing = ['input' => 3.00, 'output' => 15.00];

    $cost = TokenCost::calculate(
        inputTokens: 1000,
        outputTokens: 2000,
        pricing: $pricing,
    );

    expect($cost->totalTokens())->toBe(3000);
});

test('token cost formatted output', function () {
    $pricing = ['input' => 3.00, 'output' => 15.00];

    $cost = TokenCost::calculate(
        inputTokens: 1000,
        outputTokens: 500,
        pricing: $pricing,
    );

    expect($cost->formatted())->toBe('$0.010500');
    expect($cost->formatted('€', 4))->toBe('€0.0105');
});

test('token cost to array', function () {
    $pricing = ['input' => 3.00, 'output' => 15.00];

    $cost = TokenCost::calculate(
        inputTokens: 1000,
        outputTokens: 500,
        pricing: $pricing,
        model: 'claude-sonnet',
    );

    $array = $cost->toArray();

    expect($array['model'])->toBe('claude-sonnet');
    expect($array['input_tokens'])->toBe(1000);
    expect($array['output_tokens'])->toBe(500);
    expect($array['input_cost'])->toBe(0.003);
    expect($array['output_cost'])->toBe(0.0075);
    expect(round($array['total_cost'], 6))->toBe(0.0105);
    expect($array['total_tokens'])->toBe(1500);
});

test('claude manager estimate cost uses config pricing', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'default_model' => 'claude-sonnet-4-5-20250929',
        'pricing' => [
            'claude-sonnet' => ['input' => 3.00, 'output' => 15.00],
            'claude-opus' => ['input' => 15.00, 'output' => 75.00],
        ],
    ]);

    $cost = $manager->estimateCost(inputTokens: 1000, outputTokens: 500);

    expect($cost->inputCost)->toBe(0.003);
    expect($cost->outputCost)->toBe(0.0075);
    expect($cost->model)->toBe('claude-sonnet-4-5-20250929');
});

test('claude manager estimate cost with specific model', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'pricing' => [
            'claude-sonnet' => ['input' => 3.00, 'output' => 15.00],
            'claude-opus' => ['input' => 15.00, 'output' => 75.00],
        ],
    ]);

    $cost = $manager->estimateCost(
        inputTokens: 1000,
        outputTokens: 500,
        model: 'claude-opus-4-5-20251101'
    );

    expect($cost->inputCost)->toBe(0.015);
    expect($cost->outputCost)->toBe(0.0375);
});

test('claude manager get pricing for model matches pattern', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'pricing' => [
            'claude-haiku' => ['input' => 0.25, 'output' => 1.25],
            'claude-sonnet' => ['input' => 3.00, 'output' => 15.00],
            'claude-opus' => ['input' => 15.00, 'output' => 75.00],
        ],
    ]);

    expect($manager->getPricingForModel('claude-haiku-3-5-20251022'))->toBe(['input' => 0.25, 'output' => 1.25]);
    expect($manager->getPricingForModel('claude-sonnet-4-5-20250929'))->toBe(['input' => 3.00, 'output' => 15.00]);
    expect($manager->getPricingForModel('claude-opus-4-5-20251101'))->toBe(['input' => 15.00, 'output' => 75.00]);
});

test('claude manager get pricing falls back to sonnet for unknown model', function () {
    $manager = new ClaudeManager([
        'api_key' => 'test-key',
        'pricing' => [
            'claude-sonnet' => ['input' => 3.00, 'output' => 15.00],
        ],
    ]);

    expect($manager->getPricingForModel('unknown-model'))->toBe(['input' => 3.00, 'output' => 15.00]);
});

test('fake count tokens returns BetaMessageTokensCount', function () {
    $fake = new PendingClaudeFake;

    $result = $fake->countTokens(['messages' => []]);

    expect($result)->toBeInstanceOf(BetaMessageTokensCount::class);
    expect($result->input_tokens)->toBe(10);
});

test('fake count tokens with custom value returns BetaMessageTokensCount', function () {
    $fake = new PendingClaudeFake;
    $fake->fakeTokenCount(500);

    $result = $fake->countTokens(['messages' => []]);

    expect($result)->toBeInstanceOf(BetaMessageTokensCount::class);
    expect($result->input_tokens)->toBe(500);
});
