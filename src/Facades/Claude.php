<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Facades;

use GoldenPathDigital\Claude\ClaudeManager;
use GoldenPathDigital\Claude\Testing\PendingClaudeFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Anthropic\Services\MessagesService messages()
 * @method static \GoldenPathDigital\Claude\Conversation\ConversationBuilder conversation()
 * @method static PendingClaudeFake fake(array $responses = [])
 * @method static void clearFake()
 * @method static bool isFaking()
 *
 * @see ClaudeManager
 */
class Claude extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ClaudeManager::class;
    }
}
