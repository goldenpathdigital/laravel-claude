<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Conversation;

use Anthropic\Messages\Message;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextBlock;
use Closure;
use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Events\StreamChunk;
use GoldenPathDigital\Claude\Events\StreamComplete;

class ConversationBuilder
{
    protected ClaudeClientInterface $client;

    protected ?string $model = null;

    protected ?string $system = null;

    protected array $messages = [];

    protected int $maxTokens = 1024;

    protected ?float $temperature = null;

    public function __construct(ClaudeClientInterface $client)
    {
        $this->client = $client;
        $this->model = $client->config('default_model');
    }

    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function system(string $system): self
    {
        $this->system = $system;

        return $this;
    }

    public function user(string $content): self
    {
        $this->messages[] = [
            'role' => 'user',
            'content' => $content,
        ];

        return $this;
    }

    public function assistant(string $content): self
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $content,
        ];

        return $this;
    }

    public function maxTokens(int $tokens): self
    {
        $this->maxTokens = $tokens;

        return $this;
    }

    public function temperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    protected function buildPayload(): array
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $this->messages,
        ];

        if ($this->system !== null) {
            $payload['system'] = $this->system;
        }

        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }

        return $payload;
    }

    public function send(): Message
    {
        $payload = $this->buildPayload();

        $response = $this->client->messages()->create($payload);

        $this->appendAssistantResponse($response);

        return $response;
    }

    /** @param  Closure(StreamChunk): void  $callback */
    public function stream(Closure $callback): StreamComplete
    {
        $payload = $this->buildPayload();

        $stream = $this->client->messages()->createStream($payload);

        $fullText = '';
        $chunkIndex = 0;
        $message = null;
        $stopReason = null;

        foreach ($stream as $event) {
            if ($event instanceof RawMessageStartEvent) {
                $message = $event->message;
            }

            if ($event instanceof RawContentBlockDeltaEvent) {
                $delta = $event->delta;

                if (isset($delta->text)) {
                    $text = $delta->text;
                    $fullText .= $text;

                    $chunk = new StreamChunk(
                        text: $text,
                        index: $chunkIndex++,
                        type: $delta->type ?? 'text_delta',
                    );

                    $callback($chunk);

                    if (function_exists('event')) {
                        event($chunk);
                    }
                }
            }

            if ($event instanceof RawMessageDeltaEvent) {
                $stopReason = $event->delta->stop_reason ?? null;
            }
        }

        if ($fullText !== '') {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $fullText,
            ];
        }

        $complete = new StreamComplete(
            message: $message,
            fullText: $fullText,
            stopReason: $stopReason,
        );

        if (function_exists('event')) {
            event($complete);
        }

        return $complete;
    }

    protected function appendAssistantResponse(Message $response): void
    {
        if (! empty($response->content)) {
            $firstBlock = $response->content[0] ?? null;

            if ($firstBlock instanceof TextBlock) {
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => $firstBlock->text,
                ];
            }
        }
    }

    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'system' => $this->system,
            'messages' => $this->messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];
    }
}
