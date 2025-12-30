<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Events;

use GoldenPathDigital\Claude\Contracts\StreamEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamChunk implements StreamEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $text,
        public readonly int $index,
        public readonly string $type = 'text_delta',
    ) {}
}
