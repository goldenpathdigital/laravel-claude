<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Contracts;

use GoldenPathDigital\Claude\Conversation\ConversationBuilder;

interface ClaudeClientInterface
{
    public function messages(): mixed;

    public function conversation(): ConversationBuilder;

    public function config(string $key, mixed $default = null): mixed;
}
