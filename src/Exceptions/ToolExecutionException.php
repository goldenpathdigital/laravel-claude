<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Exceptions;

use RuntimeException;
use Throwable;

class ToolExecutionException extends RuntimeException
{
    protected string $toolName;

    /** @var array<string, mixed> */
    protected array $toolInput;

    /** @param array<string, mixed> $input */
    public function __construct(
        string $toolName,
        array $input,
        string $message,
        ?Throwable $previous = null
    ) {
        $this->toolName = $toolName;
        $this->toolInput = $input;
        parent::__construct($message, 0, $previous);
    }

    public function getToolName(): string
    {
        return $this->toolName;
    }

    /** @return array<string, mixed> */
    public function getToolInput(): array
    {
        return $this->toolInput;
    }
}
