<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Tools;

use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\ToolUseBlock;
use GoldenPathDigital\Claude\Exceptions\ToolExecutionException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

class ToolExecutor
{
    /** @var array<Tool> */
    protected array $tools;

    protected LoggerInterface $logger;

    protected ?float $defaultTimeout;

    /** @param array<Tool> $tools */
    public function __construct(array $tools = [], ?LoggerInterface $logger = null, ?float $defaultTimeout = null)
    {
        $this->tools = $tools;
        $this->logger = $logger ?? new NullLogger;
        $this->defaultTimeout = $defaultTimeout;
    }

    /** @param array<Tool> $tools */
    public function setTools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function executeToolsFromResponse(Message $response): array
    {
        $results = [];

        foreach ($response->content as $block) {
            if (! $block instanceof ToolUseBlock) {
                continue;
            }

            $results[] = $this->executeToolBlock($block);
        }

        return $results;
    }

    /** @return array<string, mixed> */
    protected function executeToolBlock(ToolUseBlock $block): array
    {
        $tool = $this->findTool($block->name);

        if ($tool === null || ! $tool->hasHandler()) {
            $this->logger->warning("Tool not found or has no handler: {$block->name}");

            return [
                'type' => 'tool_result',
                'tool_use_id' => $block->id,
                'content' => "Tool '{$block->name}' not found or has no handler",
                'is_error' => true,
            ];
        }

        try {
            $input = is_array($block->input) ? $block->input : [];
            $result = $this->executeWithTimeout($tool, $input);

            $content = $result;
            if (! is_string($result)) {
                $content = json_encode($result, JSON_THROW_ON_ERROR);
            }

            return [
                'type' => 'tool_result',
                'tool_use_id' => $block->id,
                'content' => $content,
            ];
        } catch (\JsonException $e) {
            $this->logger->error("Tool result JSON encoding failed: {$block->name}", [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [
                'type' => 'tool_result',
                'tool_use_id' => $block->id,
                'content' => 'Error: Tool result could not be encoded as JSON',
                'is_error' => true,
            ];
        } catch (Throwable $e) {
            $this->logger->error("Tool execution failed: {$block->name}", [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [
                'type' => 'tool_result',
                'tool_use_id' => $block->id,
                'content' => "Error: {$e->getMessage()}",
                'is_error' => true,
            ];
        }
    }

    /** @param array<string, mixed> $input */
    protected function executeWithTimeout(Tool $tool, array $input): mixed
    {
        $timeout = $tool->getTimeout() ?? $this->defaultTimeout;

        if ($timeout === null) {
            return $tool->execute($input);
        }

        return $this->runWithPcntlTimeout($tool, $input, $timeout);
    }

    /** @param array<string, mixed> $input */
    protected function runWithPcntlTimeout(Tool $tool, array $input, float $timeout): mixed
    {
        if (! extension_loaded('pcntl') || PHP_SAPI !== 'cli') {
            return $tool->execute($input);
        }

        $timeoutSeconds = (int) ceil($timeout);
        $timedOut = false;

        $previousHandler = pcntl_signal_get_handler(SIGALRM);

        pcntl_signal(SIGALRM, function () use (&$timedOut): void {
            $timedOut = true;
        });

        pcntl_alarm($timeoutSeconds);

        try {
            $result = $tool->execute($input);
            pcntl_alarm(0);

            pcntl_signal_dispatch();

            if ($timedOut) {
                throw new ToolExecutionException(
                    $tool->getName(),
                    $input,
                    "Tool '{$tool->getName()}' execution timed out"
                );
            }

            return $result;
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $previousHandler ?: SIG_DFL);
        }
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

    /** @return array<int, array<string, mixed>> */
    public function buildToolInteractionMessages(Message $response, array $toolResults): array
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

        return [
            ['role' => 'assistant', 'content' => $assistantContent],
            ['role' => 'user', 'content' => $toolResults],
        ];
    }

    public function hasExecutableTools(Message $response): bool
    {
        foreach ($response->content as $block) {
            if ($block instanceof ToolUseBlock) {
                $tool = $this->findTool($block->name);
                if ($tool !== null && $tool->hasHandler()) {
                    return true;
                }
            }
        }

        return false;
    }
}
