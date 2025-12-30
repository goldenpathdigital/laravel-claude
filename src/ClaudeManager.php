<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude;

use Anthropic\Beta\Messages\BetaMessageTokensCount;
use Anthropic\Client;
use Anthropic\RequestOptions;
use Anthropic\Services\Beta\FilesService;
use Anthropic\Services\Beta\Messages\BatchesService;
use Anthropic\Services\MessagesService;
use Anthropic\Services\ModelsService;
use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use GoldenPathDigital\Claude\Exceptions\ConfigurationException;
use GoldenPathDigital\Claude\Testing\FakeResponse;
use GoldenPathDigital\Claude\Testing\FakeScope;
use GoldenPathDigital\Claude\Testing\PendingClaudeFake;
use GoldenPathDigital\Claude\ValueObjects\TokenCost;

class ClaudeManager implements ClaudeClientInterface
{
    protected Client $client;

    /** @var array<string, mixed> */
    protected array $config;

    protected RequestOptions $requestOptions;

    protected static ?PendingClaudeFake $fake = null;

    /** @var array<string> */
    public const KNOWN_MODELS = [
        'claude-3-opus-20240229',
        'claude-3-sonnet-20240229',
        'claude-3-haiku-20240307',
        'claude-3-5-sonnet-20240620',
        'claude-3-5-sonnet-20241022',
        'claude-3-5-haiku-20241022',
        'claude-sonnet-4-5-20250929',
        'claude-opus-4-20250514',
        'claude-sonnet-4-20250514',
    ];

    /**
     * @param array<string, mixed> $config
     *
     * @throws ConfigurationException
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->validateConfig();
        $this->requestOptions = $this->buildRequestOptions();
        $this->client = $this->createClient();
    }

    /** @throws ConfigurationException */
    protected function validateConfig(): void
    {
        $apiKey = $this->config['api_key'] ?? null;
        $authToken = $this->config['auth_token'] ?? null;

        if (empty($apiKey) && empty($authToken)) {
            throw new ConfigurationException(
                'Either ANTHROPIC_API_KEY or ANTHROPIC_AUTH_TOKEN must be configured. '.
                'Set one of these in your .env file or config/claude.php.'
            );
        }
    }

    protected function buildRequestOptions(): RequestOptions
    {
        $timeout = $this->config['timeout'] ?? null;
        $maxRetries = $this->config['max_retries'] ?? null;
        $betaHeaders = $this->betaHeaders();

        return RequestOptions::with(
            timeout: is_numeric($timeout) ? (float) $timeout : null,
            maxRetries: is_numeric($maxRetries) ? (int) $maxRetries : null,
            extraHeaders: ! empty($betaHeaders) ? $betaHeaders : null,
        );
    }

    protected function createClient(): Client
    {
        return new Client(
            apiKey: $this->config['api_key'] ?? null,
            authToken: $this->config['auth_token'] ?? null,
            baseUrl: $this->config['base_url'] ?? null,
        );
    }

    /** @return array<string, string> */
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

    public function getRequestOptions(): RequestOptions
    {
        return $this->requestOptions;
    }

    /** @param array<string, mixed> $overrides */
    public function getRequestOptionsWith(array $overrides = []): RequestOptions
    {
        return RequestOptions::with(
            timeout: $overrides['timeout'] ?? $this->requestOptions->timeout,
            maxRetries: $overrides['maxRetries'] ?? $this->requestOptions->maxRetries,
            extraHeaders: array_merge(
                $this->requestOptions->extraHeaders ?? [],
                $overrides['extraHeaders'] ?? []
            ),
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

    public function countTokens(array $params): BetaMessageTokensCount
    {
        return $this->client->beta->messages->countTokens($params);
    }

    public function estimateCost(
        int $inputTokens,
        int $outputTokens = 0,
        ?string $model = null,
    ): TokenCost {
        $model = $model ?? $this->config['default_model'] ?? 'claude-sonnet-4-5-20250929';
        $pricing = $this->getPricingForModel($model);

        return TokenCost::calculate($inputTokens, $outputTokens, $pricing, $model);
    }

    /** @return array{input: float, output: float} */
    public function getPricingForModel(string $model): array
    {
        $pricingConfig = $this->config['pricing'] ?? [];

        foreach ($pricingConfig as $pattern => $pricing) {
            if (str_contains(strtolower($model), $pattern)) {
                return $pricing;
            }
        }

        return $pricingConfig['claude-sonnet'] ?? ['input' => 3.00, 'output' => 15.00];
    }

    public static function isKnownModel(string $model): bool
    {
        if (in_array($model, self::KNOWN_MODELS, true)) {
            return true;
        }

        $patterns = [
            '/^claude-3-opus-\d{8}$/',
            '/^claude-3-sonnet-\d{8}$/',
            '/^claude-3-haiku-\d{8}$/',
            '/^claude-3-5-sonnet-\d{8}$/',
            '/^claude-3-5-haiku-\d{8}$/',
            '/^claude-sonnet-4-5-\d{8}$/',
            '/^claude-opus-4-\d{8}$/',
            '/^claude-sonnet-4-\d{8}$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $model)) {
                return true;
            }
        }

        return false;
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

        if (function_exists('app') && app()->bound('app')) {
            app()->instance(ClaudeManager::class, static::$fake);
        }

        return static::$fake;
    }

    public static function clearFake(): void
    {
        static::$fake = null;

        if (function_exists('app') && app()->bound(ClaudeManager::class)) {
            app()->forgetInstance(ClaudeManager::class);
        }
    }

    public static function isFaking(): bool
    {
        return static::$fake !== null;
    }

    public static function getFake(): ?PendingClaudeFake
    {
        return static::$fake;
    }

    /** @param array<FakeResponse|string> $responses */
    public static function fakeScoped(array $responses = []): FakeScope
    {
        $fake = static::fake($responses);

        return new FakeScope($fake);
    }

    public static function isValidModel(string $model, bool $strict = false): bool
    {
        if (! $strict) {
            return true;
        }

        return self::isKnownModel($model);
    }
}
