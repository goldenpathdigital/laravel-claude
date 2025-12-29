<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude;

use Anthropic\Client;
use Anthropic\Services\MessagesService;
use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use GoldenPathDigital\Claude\Testing\FakeResponse;
use GoldenPathDigital\Claude\Testing\PendingClaudeFake;

class ClaudeManager implements ClaudeClientInterface
{
    protected Client $client;

    protected array $config;

    protected static ?PendingClaudeFake $fake = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = $this->createClient();
    }

    protected function createClient(): Client
    {
        return new Client(
            apiKey: $this->config['api_key'] ?? null,
        );
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function messages(): MessagesService
    {
        return $this->client->messages;
    }

    public function conversation(): ConversationBuilder
    {
        if (static::$fake !== null) {
            return static::$fake->conversation();
        }

        return new ConversationBuilder($this);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /** @param array<FakeResponse|string> $responses */
    public static function fake(array $responses = []): PendingClaudeFake
    {
        static::$fake = new PendingClaudeFake($responses, [
            'default_model' => 'claude-sonnet-4-5-20250929',
        ]);

        if (function_exists('app')) {
            app()->instance(ClaudeManager::class, static::$fake);
        }

        return static::$fake;
    }

    public static function clearFake(): void
    {
        static::$fake = null;
    }

    public static function isFaking(): bool
    {
        return static::$fake !== null;
    }

    public static function getFake(): ?PendingClaudeFake
    {
        return static::$fake;
    }
}
