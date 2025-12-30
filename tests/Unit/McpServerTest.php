<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\MCP\McpServer;

test('creates server with url', function () {
    $server = McpServer::url('https://mcp.example.com/server')
        ->name('test-server');

    $array = $server->toArray();

    expect($array['type'])->toBe('url');
    expect($array['url'])->toBe('https://mcp.example.com/server');
});

test('sets server name', function () {
    $server = McpServer::url('https://example.com')
        ->name('my-server');

    expect($server->getName())->toBe('my-server');
    expect($server->toArray()['name'])->toBe('my-server');
});

test('sets authorization token', function () {
    $server = McpServer::url('https://example.com')
        ->name('test-server')
        ->token('secret-token-123');

    $array = $server->toArray();

    expect($array['authorization_token'])->toBe('secret-token-123');
});

test('configures allowed tools', function () {
    $server = McpServer::url('https://example.com')
        ->name('test-server')
        ->allowTools(['tool_a', 'tool_b']);

    // Server array should not contain tool_configuration (deprecated pattern)
    $serverArray = $server->toArray();
    expect($serverArray)->not->toHaveKey('tool_configuration');

    // Toolset array should have the mcp_toolset format
    $toolsetArray = $server->toToolsetArray();
    expect($toolsetArray['type'])->toBe('mcp_toolset');
    expect($toolsetArray['mcp_server_name'])->toBe('test-server');
    expect($toolsetArray['default_config']['enabled'])->toBeFalse();
    expect($toolsetArray['configs']['tool_a']['enabled'])->toBeTrue();
    expect($toolsetArray['configs']['tool_b']['enabled'])->toBeTrue();
});

test('configures denied tools', function () {
    $server = McpServer::url('https://example.com')
        ->name('test-server')
        ->denyTools(['dangerous_tool']);

    // Server array should not contain tool_configuration (deprecated pattern)
    $serverArray = $server->toArray();
    expect($serverArray)->not->toHaveKey('tool_configuration');

    // Toolset array should have the mcp_toolset format
    $toolsetArray = $server->toToolsetArray();
    expect($toolsetArray['type'])->toBe('mcp_toolset');
    expect($toolsetArray['mcp_server_name'])->toBe('test-server');
    expect($toolsetArray['default_config']['enabled'])->toBeTrue();
    expect($toolsetArray['configs']['dangerous_tool']['enabled'])->toBeFalse();
});

test('creates from config array', function () {
    $config = [
        'url' => 'https://zapier.mcp.com/actions',
        'name' => 'zapier',
        'token' => 'zapier-api-key',
        'allowed_tools' => ['gmail_send', 'slack_post'],
    ];

    $server = McpServer::fromConfig('zapier-server', $config);

    $serverArray = $server->toArray();

    expect($serverArray['url'])->toBe('https://zapier.mcp.com/actions');
    expect($serverArray['name'])->toBe('zapier');
    expect($serverArray['authorization_token'])->toBe('zapier-api-key');
    expect($serverArray)->not->toHaveKey('tool_configuration');

    // Check toolset array for allowed tools config
    $toolsetArray = $server->toToolsetArray();
    expect($toolsetArray['type'])->toBe('mcp_toolset');
    expect($toolsetArray['mcp_server_name'])->toBe('zapier');
    expect($toolsetArray['configs']['gmail_send']['enabled'])->toBeTrue();
    expect($toolsetArray['configs']['slack_post']['enabled'])->toBeTrue();
});

test('uses config key as name when name not specified', function () {
    $config = [
        'url' => 'https://example.com',
    ];

    $server = McpServer::fromConfig('default-name', $config);

    expect($server->getName())->toBe('default-name');
});

test('prefers explicit name over config key', function () {
    $config = [
        'url' => 'https://example.com',
        'name' => 'explicit-name',
    ];

    $server = McpServer::fromConfig('config-key', $config);

    expect($server->getName())->toBe('explicit-name');
});

test('handles config with denied tools', function () {
    $config = [
        'url' => 'https://example.com',
        'denied_tools' => ['file_delete', 'system_exec'],
    ];

    $server = McpServer::fromConfig('restricted', $config);

    $serverArray = $server->toArray();
    expect($serverArray)->not->toHaveKey('tool_configuration');

    // Check toolset array for denied tools config
    $toolsetArray = $server->toToolsetArray();
    expect($toolsetArray['type'])->toBe('mcp_toolset');
    expect($toolsetArray['mcp_server_name'])->toBe('restricted');
    expect($toolsetArray['configs']['file_delete']['enabled'])->toBeFalse();
    expect($toolsetArray['configs']['system_exec']['enabled'])->toBeFalse();
});

test('fluent interface returns self', function () {
    $server = McpServer::url('https://example.com');

    expect($server->name('test'))->toBe($server);
    expect($server->token('token'))->toBe($server);
    expect($server->allowTools(['tool']))->toBe($server);
    expect($server->denyTools(['tool']))->toBe($server);
});

test('toToolsetArray returns basic mcp_toolset structure', function () {
    $server = McpServer::url('https://example.com')
        ->name('basic-server');

    $toolsetArray = $server->toToolsetArray();

    expect($toolsetArray['type'])->toBe('mcp_toolset');
    expect($toolsetArray['mcp_server_name'])->toBe('basic-server');
    expect($toolsetArray)->not->toHaveKey('default_config');
    expect($toolsetArray)->not->toHaveKey('configs');
});
