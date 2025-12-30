<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Conversation;

use GoldenPathDigital\Claude\MCP\McpServer;
use GoldenPathDigital\Claude\Tools\Tool;
use GoldenPathDigital\Claude\ValueObjects\CachedContent;

/**
 * Builds API payloads for Claude Messages API requests.
 *
 * Single source of truth for payload construction, eliminating duplication
 * between ConversationBuilder and ProcessConversation job.
 */
class PayloadBuilder
{
    /** @var array<string, mixed> */
    protected array $config;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Create a PayloadBuilder from a ConversationBuilder's configuration.
     *
     * @param  array<string, mixed>  $conversationConfig  Output from ConversationBuilder::toArray()
     */
    public static function fromConfig(array $conversationConfig): self
    {
        return new self($conversationConfig);
    }

    /**
     * Build the complete API payload.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $payload = $this->buildBasePayload();
        $payload = $this->addSystemPrompt($payload);
        $payload = $this->addSamplingParameters($payload);
        $payload = $this->addMetadata($payload);
        $payload = $this->addThinking($payload);
        $payload = $this->addTools($payload);
        $payload = $this->addMcpServers($payload);

        return $payload;
    }

    /**
     * Build the base payload with required fields.
     *
     * @return array<string, mixed>
     */
    protected function buildBasePayload(): array
    {
        return [
            'model' => $this->config['model'],
            'max_tokens' => $this->config['max_tokens'],
            'messages' => $this->config['messages'],
        ];
    }

    /**
     * Add system prompt to payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function addSystemPrompt(array $payload): array
    {
        $system = $this->config['system'] ?? null;

        if ($system === null) {
            return $payload;
        }

        // Handle CachedContent or array format (from serialized config)
        if ($system instanceof CachedContent) {
            $payload['system'] = [$system->toArray()];
        } elseif (is_array($system)) {
            // Already converted to array (from toArray() or job serialization)
            $payload['system'] = [$system];
        } else {
            $payload['system'] = $system;
        }

        return $payload;
    }

    /**
     * Add sampling parameters (temperature, top_k, top_p, stop_sequences).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function addSamplingParameters(array $payload): array
    {
        if (isset($this->config['temperature']) && $this->config['temperature'] !== null) {
            $payload['temperature'] = $this->config['temperature'];
        }

        if (! empty($this->config['stop_sequences'])) {
            $payload['stop_sequences'] = $this->config['stop_sequences'];
        }

        if (isset($this->config['top_k']) && $this->config['top_k'] !== null) {
            $payload['top_k'] = $this->config['top_k'];
        }

        if (isset($this->config['top_p']) && $this->config['top_p'] !== null) {
            $payload['top_p'] = $this->config['top_p'];
        }

        return $payload;
    }

    /**
     * Add metadata and service tier.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function addMetadata(array $payload): array
    {
        if (isset($this->config['metadata']) && $this->config['metadata'] !== null) {
            $payload['metadata'] = $this->config['metadata'];
        }

        if (isset($this->config['service_tier']) && $this->config['service_tier'] !== null) {
            $payload['service_tier'] = $this->config['service_tier'];
        }

        return $payload;
    }

    /**
     * Add extended thinking configuration.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function addThinking(array $payload): array
    {
        $thinkingBudget = $this->config['thinking_budget'] ?? null;

        if ($thinkingBudget !== null) {
            $payload['thinking'] = [
                'type' => 'enabled',
                'budget_tokens' => $thinkingBudget,
            ];
        }

        return $payload;
    }

    /**
     * Add tools and JSON schema to payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function addTools(array $payload): array
    {
        $tools = $this->normalizeTools($this->config['tools'] ?? []);

        // Add JSON schema as a tool if configured
        $jsonSchema = $this->config['json_schema'] ?? null;
        if ($jsonSchema !== null) {
            $schemaName = $this->config['json_schema_name'] ?? 'structured_output';
            $tools[] = [
                'name' => $schemaName,
                'description' => 'Respond with structured data matching the provided schema',
                'input_schema' => $jsonSchema,
            ];
            $payload['tool_choice'] = [
                'type' => 'tool',
                'name' => $schemaName,
            ];
        }

        // Store tools temporarily for MCP toolsets to append to
        $this->config['_normalized_tools'] = $tools;

        return $payload;
    }

    /**
     * Add MCP servers and their toolsets to payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function addMcpServers(array $payload): array
    {
        $mcpServers = $this->config['mcp_servers'] ?? [];
        $mcpToolsets = $this->config['mcp_toolsets'] ?? [];
        $tools = $this->config['_normalized_tools'] ?? [];

        unset($this->config['_normalized_tools']);

        if (! empty($mcpServers)) {
            // Normalize MCP servers (could be McpServer objects or arrays)
            $payload['mcp_servers'] = array_map(function ($server) {
                return $server instanceof McpServer ? $server->toArray() : $server;
            }, $mcpServers);

            // Add MCP toolsets to tools array
            foreach ($mcpToolsets as $toolset) {
                $tools[] = $toolset instanceof McpServer ? $toolset->toToolsetArray() : $toolset;
            }
        }

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        return $payload;
    }

    /**
     * Normalize tools array - handles both Tool objects and raw arrays.
     *
     * @param  array<Tool|array<string, mixed>>  $tools
     * @return array<array<string, mixed>>
     */
    protected function normalizeTools(array $tools): array
    {
        return array_map(function ($tool) {
            return $tool instanceof Tool ? $tool->toArray() : $tool;
        }, $tools);
    }

    /**
     * Update messages in the config (for tool loop iterations).
     *
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function setMessages(array $messages): self
    {
        $this->config['messages'] = $messages;

        return $this;
    }

    /**
     * Get the current configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
