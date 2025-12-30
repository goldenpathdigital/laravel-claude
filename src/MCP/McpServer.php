<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\MCP;

use InvalidArgumentException;

class McpServer
{
    /** @var array<string, mixed> */
    protected array $config = [];

    /** @var array<string, mixed>|null */
    protected ?array $toolConfig = null;

    /** @param array<string, mixed> $config */
    protected function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function url(string $url): self
    {
        if (empty(trim($url))) {
            throw new InvalidArgumentException('MCP server URL cannot be empty');
        }

        return new self([
            'type' => 'url',
            'url' => $url,
        ]);
    }

    /** @param array<string, mixed> $serverConfig */
    public static function fromConfig(string $name, array $serverConfig): self
    {
        $url = $serverConfig['url'] ?? '';

        if (empty(trim($url))) {
            throw new InvalidArgumentException("MCP server '{$name}' has no URL configured");
        }

        $instance = self::url($url);
        $instance->name($serverConfig['name'] ?? $name);

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
        if (empty(trim($name))) {
            throw new InvalidArgumentException('MCP server name cannot be empty');
        }

        $this->config['name'] = $name;

        return $this;
    }

    public function token(string $token): self
    {
        $this->config['authorization_token'] = $token;

        return $this;
    }

    /** @param array<string> $tools */
    public function allowTools(array $tools): self
    {
        $this->toolConfig = [
            'enabled' => true,
            'allowed_tools' => $tools,
        ];

        return $this;
    }

    /** @param array<string> $tools */
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

    public function hasName(): bool
    {
        return isset($this->config['name']) && ! empty(trim($this->config['name']));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if (! $this->hasName()) {
            throw new InvalidArgumentException(
                'MCP server must have a name. Call ->name() before using the server.'
            );
        }

        return $this->config;
    }

    /** @return array<string, mixed> */
    public function toToolsetArray(): array
    {
        if (! $this->hasName()) {
            throw new InvalidArgumentException(
                'MCP server must have a name. Call ->name() before using the server.'
            );
        }

        $toolset = [
            'type' => 'mcp_toolset',
            'mcp_server_name' => $this->config['name'],
        ];

        if ($this->toolConfig !== null) {
            $defaultConfig = ['enabled' => $this->toolConfig['enabled'] ?? true];

            if (isset($this->toolConfig['allowed_tools'])) {
                $defaultConfig['enabled'] = false;
                $toolset['default_config'] = $defaultConfig;

                $configs = [];
                foreach ($this->toolConfig['allowed_tools'] as $toolName) {
                    $configs[$toolName] = ['enabled' => true];
                }
                if (! empty($configs)) {
                    $toolset['configs'] = $configs;
                }
            } elseif (isset($this->toolConfig['denied_tools'])) {
                $defaultConfig['enabled'] = true;
                $toolset['default_config'] = $defaultConfig;

                $configs = [];
                foreach ($this->toolConfig['denied_tools'] as $toolName) {
                    $configs[$toolName] = ['enabled' => false];
                }
                if (! empty($configs)) {
                    $toolset['configs'] = $configs;
                }
            } else {
                $toolset['default_config'] = $defaultConfig;
            }
        }

        return $toolset;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        $info = $this->config;

        if (isset($info['authorization_token'])) {
            $info['authorization_token'] = '[REDACTED]';
        }

        return [
            'config' => $info,
            'toolConfig' => $this->toolConfig,
        ];
    }
}
