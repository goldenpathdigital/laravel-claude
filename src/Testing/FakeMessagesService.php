<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Testing;

use Anthropic\Messages\Message;
use Anthropic\Messages\MessageDeltaUsage;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawContentBlockStartEvent;
use Anthropic\Messages\RawContentBlockStopEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageDeltaEvent\Delta;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\RawMessageStopEvent;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextDelta;
use Generator;

class FakeMessagesService
{
    /** @var array<FakeResponse> */
    protected array $responses = [];

    protected int $responseIndex = 0;

    /** @var array<int, array<string, mixed>> */
    protected array $recorded = [];

    /** @param array<FakeResponse> $responses */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function addResponse(FakeResponse $response): void
    {
        $this->responses[] = $response;
    }

    /** @param array<string, mixed> $params */
    public function create(array $params): Message
    {
        $this->recorded[] = $params;

        if (empty($this->responses)) {
            return FakeResponse::make('Fake response')->toMessage();
        }

        $response = $this->responses[$this->responseIndex] ?? end($this->responses);

        if ($this->responseIndex < count($this->responses) - 1) {
            $this->responseIndex++;
        }

        return $response->toMessage();
    }

    /** @param array<string, mixed> $params */
    public function createStream(array $params): Generator
    {
        $this->recorded[] = $params;

        if (empty($this->responses)) {
            $response = FakeResponse::make('Fake response');
        } else {
            $response = $this->responses[$this->responseIndex] ?? end($this->responses);

            if ($this->responseIndex < count($this->responses) - 1) {
                $this->responseIndex++;
            }
        }

        yield from $this->generateStreamEvents($response);
    }

    protected function generateStreamEvents(FakeResponse $response): Generator
    {
        $message = $response->toMessage();
        $text = $response->getText();

        yield RawMessageStartEvent::with(message: $message);

        yield RawContentBlockStartEvent::with(
            content_block: TextBlock::with(text: '', citations: null),
            index: 0,
        );

        $chunks = $this->splitTextIntoChunks($text, 20);
        foreach ($chunks as $chunk) {
            yield RawContentBlockDeltaEvent::with(
                delta: TextDelta::with(text: $chunk),
                index: 0,
            );
        }

        yield RawContentBlockStopEvent::with(index: 0);

        yield RawMessageDeltaEvent::with(
            delta: Delta::with(
                stop_reason: $message->stop_reason,
                stop_sequence: null,
            ),
            usage: MessageDeltaUsage::with(
                cache_creation_input_tokens: null,
                cache_read_input_tokens: null,
                input_tokens: null,
                output_tokens: $message->usage->output_tokens,
                server_tool_use: null,
            ),
        );

        yield RawMessageStopEvent::with();
    }

    /** @return array<string> */
    protected function splitTextIntoChunks(string $text, int $chunkSize): array
    {
        if (empty($text)) {
            return [];
        }

        $chunks = [];
        $length = strlen($text);

        for ($i = 0; $i < $length; $i += $chunkSize) {
            $chunks[] = substr($text, $i, $chunkSize);
        }

        return $chunks;
    }

    /** @return array<int, array<string, mixed>> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    /** @param callable(array<string, mixed>): bool $callback */
    public function assertSent(callable $callback): bool
    {
        foreach ($this->recorded as $request) {
            if ($callback($request)) {
                return true;
            }
        }

        return false;
    }

    public function assertSentCount(int $count): bool
    {
        return count($this->recorded) === $count;
    }
}
