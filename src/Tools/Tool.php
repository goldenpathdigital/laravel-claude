<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Tools;

use Anthropic\Messages\Tool as SdkTool;
use Anthropic\Messages\Tool\InputSchema;
use Closure;
use GoldenPathDigital\Claude\Exceptions\ToolExecutionException;
use GoldenPathDigital\Claude\Exceptions\ValidationException;

class Tool
{
    protected string $name;

    protected ?string $description = null;

    protected array $parameters = [];

    protected array $required = [];

    protected ?Closure $handler = null;

    protected ?Closure $validator = null;

    protected ?float $timeout = null;

    public static function make(string $name): self
    {
        $instance = new self;
        $instance->name = $name;

        return $instance;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function parameter(
        string $name,
        string $type,
        string $description = '',
        bool $required = false,
        mixed $default = null,
        ?array $enum = null,
    ): self {
        $property = [
            'type' => $type,
        ];

        if ($description !== '') {
            $property['description'] = $description;
        }

        if ($enum !== null) {
            $property['enum'] = $enum;
        }

        if ($default !== null) {
            $property['default'] = $default;
        }

        $this->parameters[$name] = $property;

        if ($required) {
            $this->required[] = $name;
        }

        return $this;
    }

    public function handler(Closure $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function validator(Closure $validator): self
    {
        $this->validator = $validator;

        return $this;
    }

    public function timeout(float $seconds): self
    {
        if ($seconds <= 0) {
            throw new ValidationException('timeout', $seconds, 'Tool timeout must be positive');
        }

        $this->timeout = $seconds;

        return $this;
    }

    public function getTimeout(): ?float
    {
        return $this->timeout;
    }

    public function execute(array $input): mixed
    {
        if ($this->handler === null) {
            throw new \RuntimeException("No handler defined for tool '{$this->name}'");
        }

        $this->validateInput($input);

        try {
            return ($this->handler)($input);
        } catch (ToolExecutionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ToolExecutionException(
                $this->name,
                $input,
                $e->getMessage(),
                $e
            );
        }
    }

    protected function validateInput(array $input): void
    {
        foreach ($this->required as $requiredParam) {
            if (! array_key_exists($requiredParam, $input)) {
                throw new ValidationException(
                    $requiredParam,
                    null,
                    "Required parameter '{$requiredParam}' is missing for tool '{$this->name}'"
                );
            }
        }

        if ($this->validator !== null) {
            $result = ($this->validator)($input);

            if ($result === false) {
                throw new ValidationException(
                    'input',
                    $input,
                    "Input validation failed for tool '{$this->name}'"
                );
            }

            if (is_string($result)) {
                throw new ValidationException('input', $input, $result);
            }
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function hasHandler(): bool
    {
        return $this->handler !== null;
    }

    public function toSdkTool(): SdkTool
    {
        $inputSchema = InputSchema::with(
            properties: $this->parameters ?: null,
            required: $this->required ?: null,
        );

        return SdkTool::with(
            input_schema: $inputSchema,
            name: $this->name,
            description: $this->description,
        );
    }

    public function toArray(): array
    {
        $tool = [
            'name' => $this->name,
            'input_schema' => [
                'type' => 'object',
                'properties' => $this->parameters ?: new \stdClass,
            ],
        ];

        if ($this->description !== null) {
            $tool['description'] = $this->description;
        }

        if (! empty($this->required)) {
            $tool['input_schema']['required'] = $this->required;
        }

        return $tool;
    }
}
