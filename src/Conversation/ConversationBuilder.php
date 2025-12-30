<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Conversation;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException as SdkAPIException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException as SdkRateLimitException;
use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use Closure;
use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Events\StreamComplete;
use GoldenPathDigital\Claude\Exceptions\ApiException;
use GoldenPathDigital\Claude\Exceptions\ConfigurationException;
use GoldenPathDigital\Claude\Exceptions\RateLimitException;
use GoldenPathDigital\Claude\Exceptions\ValidationException;
use GoldenPathDigital\Claude\MCP\McpServer;
use GoldenPathDigital\Claude\Streaming\StreamHandler;
use GoldenPathDigital\Claude\Tools\Tool;
use GoldenPathDigital\Claude\Tools\ToolExecutor;
use GoldenPathDigital\Claude\ValueObjects\CachedContent;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Throwable;

class ConversationBuilder
{
    protected ClaudeClientInterface $client;

    protected ?string $model = null;

    protected string|CachedContent|null $system = null;

    /** @var array<int, array<string, mixed>> */
    protected array $messages = [];

    protected int $maxTokens = 1024;

    protected ?float $temperature = null;

    /** @var array<Tool> */
    protected array $tools = [];

    /** @var array<McpServer> */
    protected array $mcpServers = [];

    protected int $maxSteps = 1;

    protected ?int $thinkingBudget = null;

    /** @var array<string, mixed>|null */
    protected ?array $jsonSchema = null;

    protected ?string $jsonSchemaName = null;

    /** @var array<string> */
    protected array $stopSequences = [];

    protected ?int $topK = null;

    protected ?float $topP = null;

    /** @var array<string, mixed>|null */
    protected ?array $metadata = null;

    protected ?string $serviceTier = null;

    protected ?float $timeout = null;

    protected ?LoggerInterface $logger = null;

    protected ?Dispatcher $eventDispatcher = null;

    public function __construct(ClaudeClientInterface $client)
    {
        $this->client = $client;
        $this->model = $client->config('default_model');
    }

    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function withEventDispatcher(Dispatcher $dispatcher): self
    {
        $this->eventDispatcher = $dispatcher;

        return $this;
    }

    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function system(string|CachedContent $system): self
    {
        $this->system = $system;

        return $this;
    }

    public function extendedThinking(int $budgetTokens): self
    {
        if ($budgetTokens < 1024) {
            throw new ValidationException(
                'budgetTokens',
                $budgetTokens,
                'Extended thinking budget must be at least 1024 tokens'
            );
        }

        $this->thinkingBudget = $budgetTokens;

        return $this;
    }

    /** @param array<string, mixed> $schema */
    public function schema(array $schema, string $name = 'structured_output'): self
    {
        $this->jsonSchema = $schema;
        $this->jsonSchemaName = $name;

        return $this;
    }

    /** @param string|array<int|string, mixed> $content */
    public function user(string|array $content): self
    {
        $this->messages[] = [
            'role' => 'user',
            'content' => $content,
        ];

        return $this;
    }

    public function image(string $data, string $mediaType, ?string $text = null): self
    {
        $content = [
            [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mediaType,
                    'data' => $data,
                ],
            ],
        ];

        if ($text !== null) {
            $content[] = [
                'type' => 'text',
                'text' => $text,
            ];
        }

        $this->messages[] = [
            'role' => 'user',
            'content' => $content,
        ];

        return $this;
    }

    public function imageUrl(string $url, ?string $text = null): self
    {
        McpServer::validateUrl($url);

        $content = [
            [
                'type' => 'image',
                'source' => [
                    'type' => 'url',
                    'url' => $url,
                ],
            ],
        ];

        if ($text !== null) {
            $content[] = [
                'type' => 'text',
                'text' => $text,
            ];
        }

        $this->messages[] = [
            'role' => 'user',
            'content' => $content,
        ];

        return $this;
    }

    public function pdf(string $data, ?string $text = null): self
    {
        $content = [
            [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => $data,
                ],
            ],
        ];

        if ($text !== null) {
            $content[] = [
                'type' => 'text',
                'text' => $text,
            ];
        }

        $this->messages[] = [
            'role' => 'user',
            'content' => $content,
        ];

        return $this;
    }

    public function assistant(string $content): self
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $content,
        ];

        return $this;
    }

    public function maxTokens(int $tokens): self
    {
        if ($tokens < 1) {
            throw new ValidationException('maxTokens', $tokens, 'maxTokens must be at least 1');
        }

        $this->maxTokens = $tokens;

        return $this;
    }

    public function temperature(float $temperature): self
    {
        if ($temperature < 0.0 || $temperature > 1.0) {
            throw new ValidationException(
                'temperature',
                $temperature,
                'Temperature must be between 0.0 and 1.0'
            );
        }

        $this->temperature = $temperature;

        return $this;
    }

    /** @param array<string> $sequences */
    public function stopSequences(array $sequences): self
    {
        $this->stopSequences = $sequences;

        return $this;
    }

    public function topK(int $k): self
    {
        if ($k < 1) {
            throw new ValidationException('topK', $k, 'topK must be at least 1');
        }

        $this->topK = $k;

        return $this;
    }

    public function topP(float $p): self
    {
        if ($p < 0.0 || $p > 1.0) {
            throw new ValidationException('topP', $p, 'topP must be between 0.0 and 1.0');
        }

        $this->topP = $p;

        return $this;
    }

    /** @param array<string, mixed> $metadata */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function serviceTier(string $tier): self
    {
        $this->serviceTier = $tier;

        return $this;
    }

    public function timeout(float $seconds): self
    {
        if ($seconds <= 0) {
            throw new ValidationException('timeout', $seconds, 'timeout must be positive');
        }

        $this->timeout = $seconds;

        return $this;
    }

    /** @param array<Tool> $tools */
    public function tools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    /** @param array<McpServer|string> $servers */
    public function mcp(array $servers): self
    {
        $this->mcpServers = array_map(function ($server) {
            if ($server instanceof McpServer) {
                return $server;
            }

            $configuredServers = $this->client->config('mcp_servers', []);
            if (isset($configuredServers[$server])) {
                return McpServer::fromConfig($server, $configuredServers[$server]);
            }

            throw new ValidationException(
                'mcp_server',
                $server,
                "MCP server '{$server}' not found in config"
            );
        }, $servers);

        return $this;
    }

    public function maxSteps(int $steps): self
    {
        if ($steps < 1) {
            throw new ValidationException('maxSteps', $steps, 'maxSteps must be at least 1');
        }

        $this->maxSteps = $steps;

        return $this;
    }

    public function send(): Message
    {
        if ($this->maxSteps < 1) {
            throw new \RuntimeException('maxSteps must be at least 1');
        }

        $payloadBuilder = new PayloadBuilder($this->toArray());
        $payload = $payloadBuilder->build();
        $toolExecutor = $this->createToolExecutor();
        $response = null;

        for ($step = 0; $step < $this->maxSteps; $step++) {
            $response = $this->executeApiCall($payload);

            if ($response->stop_reason !== 'tool_use') {
                $this->appendAssistantResponse($response);

                return $response;
            }

            if (! $toolExecutor->hasExecutableTools($response)) {
                $this->appendAssistantResponse($response);

                return $response;
            }

            $toolResults = $toolExecutor->executeToolsFromResponse($response);

            if (empty($toolResults)) {
                $this->appendAssistantResponse($response);

                return $response;
            }

            $interactionMessages = $toolExecutor->buildToolInteractionMessages($response, $toolResults);
            foreach ($interactionMessages as $msg) {
                $this->messages[] = $msg;
            }

            $payloadBuilder->setMessages($this->messages);
            $payload = $payloadBuilder->build();
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ApiException
     * @throws RateLimitException
     * @throws ConfigurationException
     */
    protected function executeApiCall(array $payload): Message
    {
        try {
            return $this->client->messages()->create($payload);
        } catch (SdkRateLimitException $e) {
            $retryAfter = $e->response?->getHeader('retry-after')[0] ?? null;
            throw new RateLimitException(
                'Rate limit exceeded. Please retry later.',
                $retryAfter !== null ? (int) $retryAfter : null,
                $e
            );
        } catch (AuthenticationException $e) {
            throw new ConfigurationException(
                'Invalid API credentials. Check your ANTHROPIC_API_KEY or ANTHROPIC_AUTH_TOKEN.',
                0,
                $e
            );
        } catch (APIConnectionException $e) {
            throw new ApiException(
                'Failed to connect to Claude API: '.$e->getMessage(),
                'connection_error',
                $e
            );
        } catch (SdkAPIException $e) {
            throw new ApiException(
                $e->getMessage(),
                $e->body['error']['type'] ?? null,
                $e
            );
        } catch (Throwable $e) {
            throw new ApiException(
                'Unexpected API error: '.$e->getMessage(),
                null,
                $e
            );
        }
    }

    protected function createToolExecutor(): ToolExecutor
    {
        return new ToolExecutor($this->tools, $this->logger, $this->timeout);
    }

    protected function createStreamHandler(): StreamHandler
    {
        return new StreamHandler($this->eventDispatcher);
    }

    public function stream(Closure $callback): StreamComplete
    {
        $payloadBuilder = new PayloadBuilder($this->toArray());
        $payload = $payloadBuilder->build();

        $streamHandler = $this->createStreamHandler();
        $complete = $streamHandler->stream($this->client->messages(), $payload, $callback);

        $assistantMessage = $streamHandler->getAssistantContent($complete->fullText);
        if ($assistantMessage !== null) {
            $this->messages[] = $assistantMessage;
        }

        return $complete;
    }

    protected function appendAssistantResponse(Message $response): void
    {
        if (empty($response->content)) {
            return;
        }

        $firstBlock = $response->content[0];

        if ($firstBlock instanceof TextBlock) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $firstBlock->text,
            ];
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $system = $this->system;
        if ($system instanceof CachedContent) {
            $system = $system->toArray();
        }

        return [
            'model' => $this->model,
            'system' => $system,
            'messages' => $this->messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'stop_sequences' => $this->stopSequences,
            'top_k' => $this->topK,
            'top_p' => $this->topP,
            'metadata' => $this->metadata,
            'service_tier' => $this->serviceTier,
            'tools' => array_map(fn (Tool $t) => $t->toArray(), $this->tools),
            'mcp_servers' => array_map(fn (McpServer $s) => $s->toArray(), $this->mcpServers),
            'mcp_toolsets' => array_map(fn (McpServer $s) => $s->toToolsetArray(), $this->mcpServers),
            'max_steps' => $this->maxSteps,
            'thinking_budget' => $this->thinkingBudget,
            'json_schema' => $this->jsonSchema,
            'json_schema_name' => $this->jsonSchemaName,
            'timeout' => $this->timeout,
        ];
    }

    public static function extractStructuredOutput(Message $response): ?array
    {
        foreach ($response->content as $block) {
            if ($block instanceof \Anthropic\Messages\ToolUseBlock) {
                return $block->input;
            }
        }

        return null;
    }
}
