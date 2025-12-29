<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\Testing\FakeResponse;

test('creates fake text response', function () {
    $response = FakeResponse::make('Hello from Claude!');
    $message = $response->toMessage();

    expect($message->content)->toHaveCount(1);
    expect($message->content[0]->text)->toBe('Hello from Claude!');
    expect($message->stop_reason)->toBe('end_turn');
});

test('creates fake tool use response', function () {
    $response = FakeResponse::withToolUse('get_weather', ['location' => 'Paris']);

    expect($response->getText())->toBe('');

    $message = $response->toMessage();
    expect($message->stop_reason)->toBe('tool_use');
});

test('customizes response properties', function () {
    $response = FakeResponse::make('Test response')
        ->model('claude-opus-4-0-20250514')
        ->stopReason('max_tokens')
        ->usage(100, 200);

    $message = $response->toMessage();

    expect($message->model)->toBe('claude-opus-4-0-20250514');
    expect($message->stop_reason)->toBe('max_tokens');
    expect($message->usage->input_tokens)->toBe(100);
    expect($message->usage->output_tokens)->toBe(200);
});
