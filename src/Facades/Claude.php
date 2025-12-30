<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Facades;

use Anthropic\Client;
use Anthropic\RequestOptions;
use GoldenPathDigital\Claude\ClaudeManager;
use GoldenPathDigital\Claude\Testing\PendingClaudeFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Anthropic\Services\MessagesService messages()
 * @method static \Anthropic\Services\ModelsService models()
 * @method static \Anthropic\Services\Beta\Messages\BatchesService batches()
 * @method static \Anthropic\Services\Beta\FilesService files()
 * @method static \Anthropic\Beta\Messages\BetaMessageTokensCount countTokens(array $params)
 * @method static \GoldenPathDigital\Claude\ValueObjects\TokenCost estimateCost(int $inputTokens, int $outputTokens = 0, ?string $model = null)
 * @method static array{input: float, output: float} getPricingForModel(string $model)
 * @method static \GoldenPathDigital\Claude\Conversation\ConversationBuilder conversation()
 * @method static Client client()
 * @method static mixed config(string $key, mixed $default = null)
 * @method static RequestOptions getRequestOptions()
 * @method static RequestOptions getRequestOptionsWith(array $overrides = [])
 * @method static bool isKnownModel(string $model)
 * @method static PendingClaudeFake fake(array $responses = [])
 * @method static void clearFake()
 * @method static bool isFaking()
 * @method static PendingClaudeFake|null getFake()
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
