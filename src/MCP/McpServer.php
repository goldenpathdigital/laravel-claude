<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\MCP;

use GoldenPathDigital\Claude\Exceptions\ValidationException;

class McpServer
{
    /**
     * Blocked hosts for SSRF prevention.
     *
     * @var array<string>
     */
    protected const BLOCKED_HOSTS = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        '169.254.169.254', // AWS/cloud metadata endpoint
    ];

    /**
     * Private IP ranges (CIDR notation patterns).
     *
     * @var array<string>
     */
    protected const PRIVATE_IP_PATTERNS = [
        '/^10\./',           // 10.0.0.0/8
        '/^172\.(1[6-9]|2[0-9]|3[0-1])\./', // 172.16.0.0/12
        '/^192\.168\./',     // 192.168.0.0/16
        '/^127\./',          // 127.0.0.0/8 (loopback)
        '/^0\./',            // 0.0.0.0/8
        '/^169\.254\./',     // 169.254.0.0/16 (link-local)
    ];

    /** @var array<string, mixed> */
    protected array $config = [];

    /** @var array<string, mixed>|null */
    protected ?array $toolConfig = null;

    /** @param array<string, mixed> $config */
    protected function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Create an MCP server with a URL.
     *
     * @throws ValidationException If URL is invalid or targets internal resources
     */
    public static function url(string $url): self
    {
        $url = trim($url);

        if (empty($url)) {
            throw new ValidationException('url', $url, 'MCP server URL cannot be empty');
        }

        self::validateUrl($url);

        return new self([
            'type' => 'url',
            'url' => $url,
        ]);
    }

    /**
     * Validate a URL for security concerns (SSRF prevention).
     *
     * @throws ValidationException If URL is invalid or targets internal resources
     */
    public static function validateUrl(string $url): void
    {
        // Check URL format
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ValidationException('url', $url, 'Invalid MCP server URL format');
        }

        $parsed = parse_url($url);
        if ($parsed === false || ! isset($parsed['host'])) {
            throw new ValidationException('url', $url, 'Unable to parse MCP server URL');
        }

        $host = strtolower($parsed['host']);

        // Check against blocked hosts
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new ValidationException(
                'url',
                $url,
                'MCP server URL cannot target local or internal hosts'
            );
        }

        // Check if host is an IP address and validate against private ranges
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            foreach (self::PRIVATE_IP_PATTERNS as $pattern) {
                if (preg_match($pattern, $host)) {
                    throw new ValidationException(
                        'url',
                        $url,
                        'MCP server URL cannot target private IP addresses'
                    );
                }
            }
        }

        // Ensure HTTPS for security (warn but don't block for flexibility)
        $scheme = strtolower($parsed['scheme'] ?? '');
        if ($scheme !== 'https' && $scheme !== 'http') {
            throw new ValidationException(
                'url',
                $url,
                'MCP server URL must use HTTP or HTTPS protocol'
            );
        }
    }

    /** @param array<string, mixed> $serverConfig */
    public static function fromConfig(string $name, array $serverConfig): self
    {
        $url = $serverConfig['url'] ?? '';

        if (empty(trim($url))) {
            throw new ValidationException('url', $url, "MCP server '{$name}' has no URL configured");
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
            throw new ValidationException('name', $name, 'MCP server name cannot be empty');
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
            throw new ValidationException(
                'name',
                null,
                'MCP server must have a name. Call ->name() before using the server.'
            );
        }

        return $this->config;
    }

    /** @return array<string, mixed> */
    public function toToolsetArray(): array
    {
        if (! $this->hasName()) {
            throw new ValidationException(
                'name',
                null,
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
