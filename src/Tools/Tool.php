<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Tools;

use Anthropic\Messages\Tool as SdkTool;
use Anthropic\Messages\Tool\InputSchema;
use Closure;

class Tool
{
    protected string $name;

    protected ?string $description = null;

    protected array $parameters = [];

    protected array $required = [];

    protected ?Closure $handler = null;

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

    public function execute(array $input): mixed
    {
        if ($this->handler === null) {
            throw new \RuntimeException("No handler defined for tool '{$this->name}'");
        }

        return ($this->handler)($input);
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
