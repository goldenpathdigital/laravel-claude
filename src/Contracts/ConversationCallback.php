<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Contracts;

use Anthropic\Messages\Message;
use Throwable;

interface ConversationCallback
{
    public function onSuccess(Message $response, array $context = []): void;

    public function onFailure(Throwable $exception, array $context = []): void;
}
