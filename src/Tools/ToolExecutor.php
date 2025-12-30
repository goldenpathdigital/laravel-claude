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

            return [
                'type' => 'tool_result',
                'tool_use_id' => $block->id,
                'content' => is_string($result) ? $result : json_encode($result),
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
        if (! extension_loaded('pcntl')) {
            return $tool->execute($input);
        }

        $timeoutSeconds = (int) ceil($timeout);
        $previousHandler = null;
        $timedOut = false;

        $previousHandler = pcntl_signal_get_handler(SIGALRM);

        pcntl_signal(SIGALRM, function () use (&$timedOut, $tool): void {
            $timedOut = true;
            throw new ToolExecutionException(
                $tool->getName(),
                [],
                "Tool '{$tool->getName()}' execution timed out"
            );
        });

        pcntl_alarm($timeoutSeconds);

        try {
            $result = $tool->execute($input);
            pcntl_alarm(0);

            return $result;
        } finally {
            pcntl_alarm(0);
            if ($previousHandler !== null) {
                pcntl_signal(SIGALRM, $previousHandler);
            }
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
