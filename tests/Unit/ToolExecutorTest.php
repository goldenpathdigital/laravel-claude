<?php

declare(strict_types=1);

use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\ToolUseBlock;
use Anthropic\Messages\Usage;
use GoldenPathDigital\Claude\Tools\Tool;
use GoldenPathDigital\Claude\Tools\ToolExecutor;

function createToolUseMessage(string $toolName, array $input = [], string $text = ''): Message
{
    $content = [];

    if ($text !== '') {
        $content[] = TextBlock::with(citations: null, text: $text);
    }

    $content[] = ToolUseBlock::with(
        id: 'toolu_test_'.bin2hex(random_bytes(4)),
        input: $input,
        name: $toolName
    );

    return Message::with(
        id: 'msg_test_'.bin2hex(random_bytes(4)),
        content: $content,
        model: 'claude-sonnet-4-5-20250929',
        stop_reason: 'tool_use',
        stop_sequence: null,
        usage: Usage::with(
            cache_creation: null,
            cache_creation_input_tokens: null,
            cache_read_input_tokens: null,
            input_tokens: 10,
            output_tokens: 20,
            server_tool_use: null,
            service_tier: null,
        ),
    );
}

test('executes tool handler and returns result', function () {
    $tool = Tool::make('add')
        ->parameter('a', 'number', required: true)
        ->parameter('b', 'number', required: true)
        ->handler(fn (array $input) => $input['a'] + $input['b']);

    $executor = new ToolExecutor([$tool]);
    $message = createToolUseMessage('add', ['a' => 5, 'b' => 3]);

    $results = $executor->executeToolsFromResponse($message);

    expect($results)->toHaveCount(1);
    expect($results[0]['type'])->toBe('tool_result');
    expect($results[0]['content'])->toBe('8');
    expect($results[0])->not->toHaveKey('is_error');
});

test('returns error for unknown tool', function () {
    $executor = new ToolExecutor([]);
    $message = createToolUseMessage('unknown_tool', []);

    $results = $executor->executeToolsFromResponse($message);

    expect($results)->toHaveCount(1);
    expect($results[0]['is_error'])->toBeTrue();
    expect($results[0]['content'])->toContain('not found');
});

test('returns error for tool without handler', function () {
    $tool = Tool::make('no_handler')
        ->description('Tool without handler');

    $executor = new ToolExecutor([$tool]);
    $message = createToolUseMessage('no_handler', []);

    $results = $executor->executeToolsFromResponse($message);

    expect($results)->toHaveCount(1);
    expect($results[0]['is_error'])->toBeTrue();
    expect($results[0]['content'])->toContain('no handler');
});

test('catches handler exceptions and returns error', function () {
    $tool = Tool::make('failing_tool')
        ->handler(function () {
            throw new RuntimeException('Something went wrong');
        });

    $executor = new ToolExecutor([$tool]);
    $message = createToolUseMessage('failing_tool', []);

    $results = $executor->executeToolsFromResponse($message);

    expect($results)->toHaveCount(1);
    expect($results[0]['is_error'])->toBeTrue();
    expect($results[0]['content'])->toContain('Something went wrong');
});

test('builds tool interaction messages correctly', function () {
    $tool = Tool::make('greet')
        ->handler(fn ($input) => 'Hello, '.$input['name']);

    $executor = new ToolExecutor([$tool]);
    $message = createToolUseMessage('greet', ['name' => 'World'], 'Let me greet you');

    $results = $executor->executeToolsFromResponse($message);
    $interactionMessages = $executor->buildToolInteractionMessages($message, $results);

    expect($interactionMessages)->toHaveCount(2);
    expect($interactionMessages[0]['role'])->toBe('assistant');
    expect($interactionMessages[1]['role'])->toBe('user');
    expect($interactionMessages[1]['content'])->toBe($results);
});

test('hasExecutableTools returns true when tool has handler', function () {
    $tool = Tool::make('executable')
        ->handler(fn () => 'result');

    $executor = new ToolExecutor([$tool]);
    $message = createToolUseMessage('executable', []);

    expect($executor->hasExecutableTools($message))->toBeTrue();
});

test('hasExecutableTools returns false when no matching tool', function () {
    $executor = new ToolExecutor([]);
    $message = createToolUseMessage('nonexistent', []);

    expect($executor->hasExecutableTools($message))->toBeFalse();
});

test('setTools updates tool list', function () {
    $executor = new ToolExecutor([]);

    $tool = Tool::make('dynamic')
        ->handler(fn () => 'dynamic result');

    $executor->setTools([$tool]);
    $message = createToolUseMessage('dynamic', []);

    $results = $executor->executeToolsFromResponse($message);

    expect($results[0]['content'])->toBe('dynamic result');
});

test('tool with validator validates input', function () {
    $tool = Tool::make('validated_tool')
        ->parameter('age', 'integer', required: true)
        ->validator(function (array $input) {
            if ($input['age'] < 0) {
                return 'Age must be positive';
            }

            return true;
        })
        ->handler(fn ($input) => 'Age: '.$input['age']);

    $executor = new ToolExecutor([$tool]);
    $message = createToolUseMessage('validated_tool', ['age' => -5]);

    $results = $executor->executeToolsFromResponse($message);

    expect($results[0]['is_error'])->toBeTrue();
    expect($results[0]['content'])->toContain('Age must be positive');
});

test('tool validates required parameters', function () {
    $tool = Tool::make('required_params')
        ->parameter('name', 'string', required: true)
        ->handler(fn ($input) => 'Hello '.$input['name']);

    $executor = new ToolExecutor([$tool]);
    $message = createToolUseMessage('required_params', []);

    $results = $executor->executeToolsFromResponse($message);

    expect($results[0]['is_error'])->toBeTrue();
    expect($results[0]['content'])->toContain("Required parameter 'name' is missing");
});
