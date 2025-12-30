<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Streaming;

use Anthropic\Messages\Message;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Closure;
use GoldenPathDigital\Claude\Events\StreamChunk;
use GoldenPathDigital\Claude\Events\StreamComplete;
use GoldenPathDigital\Claude\Exceptions\StreamingException;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

class StreamHandler
{
    protected ?Dispatcher $eventDispatcher;

    public function __construct(?Dispatcher $eventDispatcher = null)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /** @param object $messages MessagesService or FakeMessagesService */
    public function stream(object $messages, array $payload, Closure $callback): StreamComplete
    {
        try {
            $stream = $messages->createStream($payload);
        } catch (Throwable $e) {
            throw new StreamingException(
                "Failed to create stream: {$e->getMessage()}",
                previous: $e
            );
        }

        $fullText = '';
        $chunkIndex = 0;
        $message = null;
        $stopReason = null;

        try {
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
                        $this->dispatchEvent($chunk);
                    }
                }

                if ($event instanceof RawMessageDeltaEvent) {
                    $stopReason = $event->delta->stop_reason ?? null;
                }
            }
        } catch (Throwable $e) {
            throw new StreamingException(
                "Stream interrupted: {$e->getMessage()}",
                previous: $e
            );
        }

        $complete = new StreamComplete(
            message: $message,
            fullText: $fullText,
            stopReason: $stopReason,
        );

        $this->dispatchEvent($complete);

        return $complete;
    }

    public function getAssistantContent(string $fullText): ?array
    {
        if ($fullText === '') {
            return null;
        }

        return [
            'role' => 'assistant',
            'content' => $fullText,
        ];
    }

    protected function dispatchEvent(object $event): void
    {
        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch($event);
        } elseif (function_exists('event')) {
            event($event);
        }
    }
}
