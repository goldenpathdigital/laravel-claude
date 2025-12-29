<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Facades;

use GoldenPathDigital\Claude\ClaudeManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Anthropic\Resources\Messages messages()
 * @method static \GoldenPathDigital\Claude\Conversation\ConversationBuilder conversation()
 *
 * @see \GoldenPathDigital\Claude\ClaudeManager
 */
class Claude extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ClaudeManager::class;
    }
}
