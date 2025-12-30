<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Jobs;

use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Contracts\ConversationCallback;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Dispatchable job that sends a serialized conversation payload in the background.
 * Local tool loops (where handlers run in the same process) are not supported in this context.
 */
/**
 * Serializes a ConversationBuilder for queued execution.
 *
 * This job only makes a single Messages API call, so it does not
 * replicate ConversationBuilder::send()'s tool loop. Any looped
 * behavior must be resolved before dispatching the job.
 */
class ProcessConversation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    protected array $config;

    /** @var class-string<ConversationCallback> */
    protected string $callbackClass;

    protected array $context;

    public function __construct(
        ConversationBuilder $conversation,
        string $callbackClass,
        array $context = []
    ) {
        $this->config = $conversation->toArray();
        $this->callbackClass = $callbackClass;
        $this->context = $context;
    }

    public function handle(ClaudeClientInterface $client): void
    {
        $payload = $this->buildPayload();

        $response = $client->messages()->create($payload);

        $callback = $this->resolveCallback();
        $callback->onSuccess($response, $this->context);
    }

    public function failed(Throwable $exception): void
    {
        $callback = $this->resolveCallback();
        $callback->onFailure($exception, $this->context);
    }

    protected function buildPayload(): array
    {
        $payload = [
            'model' => $this->config['model'],
            'max_tokens' => $this->config['max_tokens'],
            'messages' => $this->config['messages'],
        ];

        if ($this->config['system'] !== null) {
            $payload['system'] = is_array($this->config['system'])
                ? [$this->config['system']]
                : $this->config['system'];
        }

        if ($this->config['temperature'] !== null) {
            $payload['temperature'] = $this->config['temperature'];
        }

        if (! empty($this->config['stop_sequences'])) {
            $payload['stop_sequences'] = $this->config['stop_sequences'];
        }

        if ($this->config['top_k'] !== null) {
            $payload['top_k'] = $this->config['top_k'];
        }

        if ($this->config['top_p'] !== null) {
            $payload['top_p'] = $this->config['top_p'];
        }

        if ($this->config['metadata'] !== null) {
            $payload['metadata'] = $this->config['metadata'];
        }

        if ($this->config['service_tier'] !== null) {
            $payload['service_tier'] = $this->config['service_tier'];
        }

        if ($this->config['thinking_budget'] !== null) {
            $payload['thinking'] = [
                'type' => 'enabled',
                'budget_tokens' => $this->config['thinking_budget'],
            ];
        }

        $tools = $this->config['tools'] ?? [];

        if ($this->config['json_schema'] !== null) {
            $schemaName = $this->config['json_schema_name'] ?? 'structured_output';
            $tools[] = [
                'name' => $schemaName,
                'description' => 'Respond with structured data matching the provided schema',
                'input_schema' => $this->config['json_schema'],
            ];
            $payload['tool_choice'] = [
                'type' => 'tool',
                'name' => $schemaName,
            ];
        }

        // Add MCP servers and their corresponding mcp_toolset entries
        if (! empty($this->config['mcp_servers'])) {
            $payload['mcp_servers'] = $this->config['mcp_servers'];

            // Add mcp_toolset entries to tools array (required by mcp-client-2025-11-20)
            if (! empty($this->config['mcp_toolsets'])) {
                foreach ($this->config['mcp_toolsets'] as $toolset) {
                    $tools[] = $toolset;
                }
            }
        }

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        return $payload;
    }

    protected function resolveCallback(): ConversationCallback
    {
        return app($this->callbackClass);
    }
}
