<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\MCP;

class McpServer
{
    protected array $config = [];

    protected ?array $toolConfig = null;

    protected function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function url(string $url): self
    {
        return new self([
            'type' => 'url',
            'url' => $url,
        ]);
    }

    public static function fromConfig(string $name, array $serverConfig): self
    {
        $instance = self::url($serverConfig['url'] ?? '');

        if (isset($serverConfig['name'])) {
            $instance->name($serverConfig['name']);
        } else {
            $instance->name($name);
        }

        if (isset($serverConfig['token'])) {
            $instance->token($serverConfig['token']);
        }

        if (isset($serverConfig['allowed_tools'])) {
            $instance->allowTools($serverConfig['allowed_tools']);
        }

        if (isset($serverConfig['denied_tools'])) {
            $instance->denyTools($serverConfig['denied_tools']);
        }

        return $instance;
    }

    public function name(string $name): self
    {
        $this->config['name'] = $name;

        return $this;
    }

    public function token(string $token): self
    {
        $this->config['authorization_token'] = $token;

        return $this;
    }

    public function allowTools(array $tools): self
    {
        $this->toolConfig = [
            'enabled' => true,
            'allowed_tools' => $tools,
        ];

        return $this;
    }

    public function denyTools(array $tools): self
    {
        $this->toolConfig = [
            'enabled' => true,
            'denied_tools' => $tools,
        ];

        return $this;
    }

    public function getName(): ?string
    {
        return $this->config['name'] ?? null;
    }

    public function toArray(): array
    {
        $result = $this->config;

        if ($this->toolConfig !== null) {
            $result['tool_configuration'] = $this->toolConfig;
        }

        return $result;
    }
}
