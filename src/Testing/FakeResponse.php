<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Testing;

use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\ToolUseBlock;
use Anthropic\Messages\Usage;

use function random_bytes;

class FakeResponse
{
    protected string $text;

    protected ?string $toolName = null;

    protected ?array $toolInput = null;

    protected string $stopReason = 'end_turn';

    protected string $model = 'claude-sonnet-4-5-20250929';

    protected int $inputTokens = 10;

    protected int $outputTokens = 50;

    public static function make(string $text): self
    {
        $instance = new self;
        $instance->text = $text;

        return $instance;
    }

    public static function withToolUse(string $toolName, array $input = []): self
    {
        $instance = new self;
        $instance->text = '';
        $instance->toolName = $toolName;
        $instance->toolInput = $input;
        $instance->stopReason = 'tool_use';

        return $instance;
    }

    public function stopReason(string $reason): self
    {
        $this->stopReason = $reason;

        return $this;
    }

    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function usage(int $inputTokens, int $outputTokens): self
    {
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;

        return $this;
    }

    public function toMessage(): Message
    {
        $usage = Usage::with(
            cache_creation: null,
            cache_creation_input_tokens: null,
            cache_read_input_tokens: null,
            input_tokens: $this->inputTokens,
            output_tokens: $this->outputTokens,
            server_tool_use: null,
            service_tier: null,
        );

        $content = [];

        if ($this->text !== '') {
            $textBlock = TextBlock::with(
                citations: null,
                text: $this->text,
            );
            $content[] = $textBlock;
        }

        if ($this->toolName !== null) {
            $toolBlock = ToolUseBlock::with(
                id: 'toolu_fake_'.bin2hex(random_bytes(8)),
                input: $this->toolInput ?? [],
                name: $this->toolName,
            );
            $content[] = $toolBlock;
        }

        return Message::with(
            id: 'msg_fake_'.bin2hex(random_bytes(8)),
            content: $content,
            model: $this->model,
            stop_reason: $this->stopReason,
            stop_sequence: null,
            usage: $usage,
        );
    }

    public function getText(): string
    {
        return $this->text;
    }
}
