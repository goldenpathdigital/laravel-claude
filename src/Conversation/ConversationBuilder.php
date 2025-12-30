<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Conversation;

use Anthropic\Messages\Message;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\ToolUseBlock;
use Closure;
use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Events\StreamChunk;
use GoldenPathDigital\Claude\Events\StreamComplete;
use GoldenPathDigital\Claude\MCP\McpServer;
use GoldenPathDigital\Claude\Tools\Tool;
use GoldenPathDigital\Claude\ValueObjects\CachedContent;

class ConversationBuilder
{
    protected ClaudeClientInterface $client;

    protected ?string $model = null;

    protected string|CachedContent|null $system = null;

    protected array $messages = [];

    protected int $maxTokens = 1024;

    protected ?float $temperature = null;

    /** @var array<Tool> */
    protected array $tools = [];

    /** @var array<McpServer> */
    protected array $mcpServers = [];

    protected int $maxSteps = 1;

    protected ?int $thinkingBudget = null;

    protected ?array $jsonSchema = null;

    protected ?string $jsonSchemaName = null;

    protected array $stopSequences = [];

    protected ?int $topK = null;

    protected ?float $topP = null;

    protected ?array $metadata = null;

    protected ?string $serviceTier = null;

    public function __construct(ClaudeClientInterface $client)
    {
        $this->client = $client;
        $this->model = $client->config('default_model');
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
            throw new \InvalidArgumentException(
                'Extended thinking budget must be at least 1024 tokens'
            );
        }

        $this->thinkingBudget = $budgetTokens;

        return $this;
    }

    public function schema(array $schema, string $name = 'structured_output'): self
    {
        $this->jsonSchema = $schema;
        $this->jsonSchemaName = $name;

        return $this;
    }

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

    /**
     * Set maximum tokens for the response.
     *
     * @throws \InvalidArgumentException If tokens is not positive
     */
    public function maxTokens(int $tokens): self
    {
        if ($tokens < 1) {
            throw new \InvalidArgumentException('maxTokens must be at least 1');
        }

        $this->maxTokens = $tokens;

        return $this;
    }

    /**
     * Set the sampling temperature (0.0 to 1.0).
     *
     * @throws \InvalidArgumentException If temperature is out of range
     */
    public function temperature(float $temperature): self
    {
        if ($temperature < 0.0 || $temperature > 1.0) {
            throw new \InvalidArgumentException(
                'Temperature must be between 0.0 and 1.0'
            );
        }

        $this->temperature = $temperature;

        return $this;
    }

    public function stopSequences(array $sequences): self
    {
        $this->stopSequences = $sequences;

        return $this;
    }

    /**
     * Set top-K sampling parameter.
     *
     * @throws \InvalidArgumentException If topK is not positive
     */
    public function topK(int $k): self
    {
        if ($k < 1) {
            throw new \InvalidArgumentException('topK must be at least 1');
        }

        $this->topK = $k;

        return $this;
    }

    /**
     * Set nucleus sampling threshold (0.0 to 1.0).
     *
     * @throws \InvalidArgumentException If topP is out of range
     */
    public function topP(float $p): self
    {
        if ($p < 0.0 || $p > 1.0) {
            throw new \InvalidArgumentException(
                'topP must be between 0.0 and 1.0'
            );
        }

        $this->topP = $p;

        return $this;
    }

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

            throw new \InvalidArgumentException("MCP server '{$server}' not found in config");
        }, $servers);

        return $this;
    }

    /**
     * Set maximum tool execution iterations.
     *
     * @throws \InvalidArgumentException If steps is not positive
     */
    public function maxSteps(int $steps): self
    {
        if ($steps < 1) {
            throw new \InvalidArgumentException('maxSteps must be at least 1');
        }

        $this->maxSteps = $steps;

        return $this;
    }

    protected function buildPayload(): array
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $this->messages,
        ];

        if ($this->system !== null) {
            if ($this->system instanceof CachedContent) {
                $payload['system'] = [$this->system->toArray()];
            } else {
                $payload['system'] = $this->system;
            }
        }

        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }

        if (! empty($this->stopSequences)) {
            $payload['stop_sequences'] = $this->stopSequences;
        }

        if ($this->topK !== null) {
            $payload['top_k'] = $this->topK;
        }

        if ($this->topP !== null) {
            $payload['top_p'] = $this->topP;
        }

        if ($this->metadata !== null) {
            $payload['metadata'] = $this->metadata;
        }

        if ($this->serviceTier !== null) {
            $payload['service_tier'] = $this->serviceTier;
        }

        if ($this->thinkingBudget !== null) {
            $payload['thinking'] = [
                'type' => 'enabled',
                'budget_tokens' => $this->thinkingBudget,
            ];
        }

        $tools = array_map(fn (Tool $tool) => $tool->toArray(), $this->tools);

        if ($this->jsonSchema !== null) {
            $tools[] = [
                'name' => $this->jsonSchemaName,
                'description' => 'Respond with structured data matching the provided schema',
                'input_schema' => $this->jsonSchema,
            ];
            $payload['tool_choice'] = [
                'type' => 'tool',
                'name' => $this->jsonSchemaName,
            ];
        }

        // Add MCP servers and their corresponding mcp_toolset entries
        if (! empty($this->mcpServers)) {
            $payload['mcp_servers'] = array_map(fn (McpServer $server) => $server->toArray(), $this->mcpServers);

            // Add mcp_toolset entries to tools array (required by mcp-client-2025-11-20)
            foreach ($this->mcpServers as $server) {
                $tools[] = $server->toToolsetArray();
            }
        }

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        return $payload;
    }

    /**
     * Send the conversation and return the response.
     *
     * Executes a tool loop up to maxSteps if tools are defined and Claude
     * requests tool execution. Returns the final Message response.
     *
     * @throws \RuntimeException If maxSteps is less than 1
     */
    public function send(): Message
    {
        if ($this->maxSteps < 1) {
            throw new \RuntimeException('maxSteps must be at least 1');
        }

        $payload = $this->buildPayload();
        $response = null;

        for ($step = 0; $step < $this->maxSteps; $step++) {
            $response = $this->client->messages()->create($payload);

            if ($response->stop_reason !== 'tool_use') {
                $this->appendAssistantResponse($response);

                return $response;
            }

            $toolResults = $this->executeTools($response);

            if (empty($toolResults)) {
                $this->appendAssistantResponse($response);

                return $response;
            }

            $this->appendToolInteraction($response, $toolResults);
            $payload['messages'] = $this->messages;
        }

        return $response;
    }

    protected function executeTools(Message $response): array
    {
        $results = [];

        foreach ($response->content as $block) {
            if (! $block instanceof ToolUseBlock) {
                continue;
            }

            $tool = $this->findTool($block->name);

            if ($tool === null || ! $tool->hasHandler()) {
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block->id,
                    'content' => "Tool '{$block->name}' not found or has no handler",
                    'is_error' => true,
                ];

                continue;
            }

            try {
                $result = $tool->execute($block->input);
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block->id,
                    'content' => is_string($result) ? $result : json_encode($result),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block->id,
                    'content' => "Error: {$e->getMessage()}",
                    'is_error' => true,
                ];
            }
        }

        return $results;
    }

    protected function findTool(string $name): ?Tool
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }

        return null;
    }

    protected function appendToolInteraction(Message $response, array $toolResults): void
    {
        $assistantContent = [];
        foreach ($response->content as $block) {
            if ($block instanceof TextBlock) {
                $assistantContent[] = [
                    'type' => 'text',
                    'text' => $block->text,
                ];
            } elseif ($block instanceof ToolUseBlock) {
                $assistantContent[] = [
                    'type' => 'tool_use',
                    'id' => $block->id,
                    'name' => $block->name,
                    'input' => $block->input,
                ];
            }
        }

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $assistantContent,
        ];

        $this->messages[] = [
            'role' => 'user',
            'content' => $toolResults,
        ];
    }

    /** @param  Closure(StreamChunk): void  $callback */
    public function stream(Closure $callback): StreamComplete
    {
        $payload = $this->buildPayload();

        $stream = $this->client->messages()->createStream($payload);

        $fullText = '';
        $chunkIndex = 0;
        $message = null;
        $stopReason = null;

        foreach ($stream as $event) {
            if ($event instanceof RawMessageStartEvent) {
                $message = $event->message;
            }

            if ($event instanceof RawContentBlockDeltaEvent) {
                $delta = $event->delta;

                if (isset($delta->text)) {
                    $text = $delta->text;
                    $fullText .= $text;

                    $chunk = new StreamChunk(
                        text: $text,
                        index: $chunkIndex++,
                        type: $delta->type ?? 'text_delta',
                    );

                    $callback($chunk);

                    if (function_exists('event')) {
                        event($chunk);
                    }
                }
            }

            if ($event instanceof RawMessageDeltaEvent) {
                $stopReason = $event->delta->stop_reason ?? null;
            }
        }

        if ($fullText !== '') {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $fullText,
            ];
        }

        $complete = new StreamComplete(
            message: $message,
            fullText: $fullText,
            stopReason: $stopReason,
        );

        if (function_exists('event')) {
            event($complete);
        }

        return $complete;
    }

    protected function appendAssistantResponse(Message $response): void
    {
        if (! empty($response->content)) {
            $firstBlock = $response->content[0] ?? null;

            if ($firstBlock instanceof TextBlock) {
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => $firstBlock->text,
                ];
            }
        }
    }

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
        ];
    }

    /**
     * Extract structured data from a tool_use response.
     * Use this after send() when using schema() to get the structured output.
     */
    public static function extractStructuredOutput(Message $response): ?array
    {
        foreach ($response->content as $block) {
            if ($block instanceof ToolUseBlock) {
                return $block->input;
            }
        }

        return null;
    }
}
