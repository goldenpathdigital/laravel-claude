<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Events;

use Anthropic\Messages\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamComplete
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ?Message $message,
        public readonly string $fullText,
        public readonly ?string $stopReason = null,
    ) {}
}
