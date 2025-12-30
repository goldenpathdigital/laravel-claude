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

    /**
     * Get the mcp_servers array entry (server definition only).
     */
    public function toArray(): array
    {
        // Return only server config, not tool_configuration
        // Tool configuration is now handled via mcp_toolset in the tools array
        return $this->config;
    }

    /**
     * Get the mcp_toolset entry for the tools array.
     *
     * @return array The mcp_toolset configuration
     */
    public function toToolsetArray(): array
    {
        $toolset = [
            'type' => 'mcp_toolset',
            'mcp_server_name' => $this->config['name'] ?? '',
        ];

        // Convert old tool_configuration format to new mcp_toolset format
        if ($this->toolConfig !== null) {
            $defaultConfig = ['enabled' => $this->toolConfig['enabled'] ?? true];

            // Handle allowed_tools by disabling all others (using default_config)
            if (isset($this->toolConfig['allowed_tools'])) {
                // Set default to disabled, then enable specific tools
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
                // Set default to enabled, then disable specific tools
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
}
