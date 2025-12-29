<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude;

use Anthropic\Client;
use Anthropic\RequestOptions;
use Anthropic\Services\Beta\FilesService;
use Anthropic\Services\Beta\Messages\BatchesService;
use Anthropic\Services\MessagesService;
use Anthropic\Services\ModelsService;
use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use GoldenPathDigital\Claude\Testing\FakeResponse;
use GoldenPathDigital\Claude\Testing\PendingClaudeFake;
use ReflectionClass;

class ClaudeManager implements ClaudeClientInterface
{
    protected Client $client;

    protected array $config;

    /**
     * Static fake instance for tests. Not safe for long-running workers
     * (e.g., Octane, Horizon) because the static state persists between jobs.
     * Always call clearFake() when a test completes.
     */
    protected static ?PendingClaudeFake $fake = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = $this->createClient();
    }

    protected function createClient(): Client
    {
        $client = new Client(
            apiKey: $this->config['api_key'] ?? null,
        );

        $this->applyConfigToClient($client);

        return $client;
    }

    protected function applyConfigToClient(Client $client): void
    {
        $options = $this->cloneClientOptions($client);

        $timeout = $this->config['timeout'] ?? null;
        if (is_numeric($timeout)) {
            $options->timeout = (float) $timeout;
        }

        $maxRetries = $this->config['max_retries'] ?? null;
        if (is_numeric($maxRetries)) {
            $options->maxRetries = (int) $maxRetries;
        }

        $betaHeaders = $this->betaHeaders();
        if (! empty($betaHeaders)) {
            $options->extraHeaders = array_merge($options->extraHeaders ?? [], $betaHeaders);
        }

        $this->setClientOptions($client, $options);
    }

    protected function betaHeaders(): array
    {
        $features = $this->config['beta_features'] ?? [];

        $map = [
            'mcp_connector' => 'mcp-client-2025-11-20',
            'extended_thinking' => 'extended-thinking-2024-12-17',
            'prompt_caching' => 'prompt-caching-2024-07-31',
            'structured_outputs' => 'structured-outputs-2024-12-17',
        ];

        $enabled = [];
        foreach ($features as $feature => $flag) {
            if ($flag) {
                $enabled[] = $map[$feature] ?? $feature;
            }
        }

        if (empty($enabled)) {
            return [];
        }

        return ['anthropic-beta' => implode(',', $enabled)];
    }

    protected function cloneClientOptions(Client $client): RequestOptions
    {
        $property = $this->clientOptionsProperty($client);

        /** @var RequestOptions $options */
        $options = $property->getValue($client);

        return clone $options;
    }

    protected function setClientOptions(Client $client, RequestOptions $options): void
    {
        $property = $this->clientOptionsProperty($client);
        $property->setValue($client, $options);
    }

    protected function clientOptionsProperty(Client $client): \ReflectionProperty
    {
        $class = new ReflectionClass($client);
        $base = $class->getParentClass();

        if ($base === false) {
            throw new \RuntimeException('Unable to access client options');
        }

        $property = $base->getProperty('options');
        $property->setAccessible(true);

        return $property;
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function messages(): MessagesService
    {
        return $this->client->messages;
    }

    public function models(): ModelsService
    {
        return $this->client->models;
    }

    public function batches(): BatchesService
    {
        return $this->client->beta->messages->batches;
    }

    public function files(): FilesService
    {
        return $this->client->beta->files;
    }

    public function countTokens(array $params): mixed
    {
        return $this->client->beta->messages->countTokens($params);
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
