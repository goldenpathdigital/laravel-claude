<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use GoldenPathDigital\Claude\Exceptions\StreamingException;
use GoldenPathDigital\Claude\Facades\Claude;
use GoldenPathDigital\Claude\Testing\FakeResponse;

beforeEach(function () {
    $this->mockClient = Mockery::mock(ClaudeClientInterface::class);
    $this->mockClient->shouldReceive('config')
        ->with('default_model')
        ->andReturn('claude-sonnet-4-5-20250929');
});

describe('Validation Error Handling', function () {
    test('invalid temperature throws InvalidArgumentException', function () {
        $conversation = new ConversationBuilder($this->mockClient);

        expect(fn () => $conversation->temperature(1.5))
            ->toThrow(InvalidArgumentException::class, 'Temperature must be between 0.0 and 1.0');

        expect(fn () => $conversation->temperature(-0.1))
            ->toThrow(InvalidArgumentException::class, 'Temperature must be between 0.0 and 1.0');
    });

    test('invalid maxTokens throws InvalidArgumentException', function () {
        $conversation = new ConversationBuilder($this->mockClient);

        expect(fn () => $conversation->maxTokens(0))
            ->toThrow(InvalidArgumentException::class, 'maxTokens must be at least 1');

        expect(fn () => $conversation->maxTokens(-100))
            ->toThrow(InvalidArgumentException::class, 'maxTokens must be at least 1');
    });

    test('invalid maxSteps throws InvalidArgumentException', function () {
        $conversation = new ConversationBuilder($this->mockClient);

        expect(fn () => $conversation->maxSteps(0))
            ->toThrow(InvalidArgumentException::class, 'maxSteps must be at least 1');
    });

    test('invalid topK throws InvalidArgumentException', function () {
        $conversation = new ConversationBuilder($this->mockClient);

        expect(fn () => $conversation->topK(0))
            ->toThrow(InvalidArgumentException::class, 'topK must be at least 1');
    });

    test('invalid topP throws InvalidArgumentException', function () {
        $conversation = new ConversationBuilder($this->mockClient);

        expect(fn () => $conversation->topP(1.5))
            ->toThrow(InvalidArgumentException::class, 'topP must be between 0.0 and 1.0');
    });

    test('invalid timeout throws InvalidArgumentException', function () {
        $conversation = new ConversationBuilder($this->mockClient);

        expect(fn () => $conversation->timeout(0))
            ->toThrow(InvalidArgumentException::class, 'timeout must be positive');

        expect(fn () => $conversation->timeout(-5))
            ->toThrow(InvalidArgumentException::class, 'timeout must be positive');
    });

    test('invalid extendedThinking budget throws InvalidArgumentException', function () {
        $conversation = new ConversationBuilder($this->mockClient);

        expect(fn () => $conversation->extendedThinking(budgetTokens: 100))
            ->toThrow(InvalidArgumentException::class, 'Extended thinking budget must be at least 1024 tokens');
    });

    test('unconfigured MCP server throws InvalidArgumentException', function () {
        $this->mockClient->shouldReceive('config')
            ->with('mcp_servers', [])
            ->andReturn([]);

        $conversation = new ConversationBuilder($this->mockClient);

        expect(fn () => $conversation->mcp(['nonexistent']))
            ->toThrow(InvalidArgumentException::class, "MCP server 'nonexistent' not found in config");
    });
});

describe('StreamingException', function () {
    test('StreamingException can wrap another exception', function () {
        $original = new RuntimeException('Original error');
        $streaming = new StreamingException('Stream failed', previous: $original);

        expect($streaming->getMessage())->toBe('Stream failed');
        expect($streaming->getPrevious())->toBe($original);
    });

    test('StreamingException without previous exception', function () {
        $streaming = new StreamingException('Stream failed');

        expect($streaming->getMessage())->toBe('Stream failed');
        expect($streaming->getPrevious())->toBeNull();
    });
});

describe('Fake Response Error Scenarios', function () {
    test('fake responses are consumed in order', function () {
        Claude::fake([
            FakeResponse::make('First response'),
            FakeResponse::make('Second response'),
        ]);

        $first = Claude::conversation()->user('Hello')->send();
        $second = Claude::conversation()->user('World')->send();

        expect($first->content[0]->text)->toBe('First response');
        expect($second->content[0]->text)->toBe('Second response');

        Claude::clearFake();
    });

    test('last fake response repeats when exhausted', function () {
        Claude::fake([
            FakeResponse::make('Only response'),
        ]);

        $first = Claude::conversation()->user('First')->send();
        $second = Claude::conversation()->user('Second')->send();

        expect($first->content[0]->text)->toBe('Only response');
        expect($second->content[0]->text)->toBe('Only response');

        Claude::clearFake();
    });
});

describe('Tool Execution Error Handling', function () {
    test('tool without handler returns error result', function () {
        $tool = \GoldenPathDigital\Claude\Tools\Tool::make('test_tool')
            ->description('A test tool');

        expect($tool->hasHandler())->toBeFalse();
        expect(fn () => $tool->execute(['input' => 'test']))
            ->toThrow(RuntimeException::class, "No handler defined for tool 'test_tool'");
    });

    test('tool with throwing handler propagates exception', function () {
        $tool = \GoldenPathDigital\Claude\Tools\Tool::make('failing_tool')
            ->description('A tool that fails')
            ->handler(function () {
                throw new RuntimeException('Tool execution failed');
            });

        expect(fn () => $tool->execute([]))
            ->toThrow(RuntimeException::class, 'Tool execution failed');
    });
});
