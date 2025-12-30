<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Events;

use Anthropic\Messages\Message;
use GoldenPathDigital\Claude\Contracts\StreamEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamComplete implements StreamEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ?Message $message,
        public readonly string $fullText,
        public readonly ?string $stopReason = null,
    ) {}

    public function usage(): array
    {
        if ($this->message === null) {
            return ['input_tokens' => 0, 'output_tokens' => 0];
        }

        return [
            'input_tokens' => $this->message->usage->input_tokens ?? 0,
            'output_tokens' => $this->message->usage->output_tokens ?? 0,
        ];
    }
}
