<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\MCP\McpServer;

test('creates server with url', function () {
    $server = McpServer::url('https://mcp.example.com/server');

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
        ->token('secret-token-123');

    $array = $server->toArray();

    expect($array['authorization_token'])->toBe('secret-token-123');
});

test('configures allowed tools', function () {
    $server = McpServer::url('https://example.com')
        ->allowTools(['tool_a', 'tool_b']);

    $array = $server->toArray();

    expect($array['tool_configuration'])->toBe([
        'enabled' => true,
        'allowed_tools' => ['tool_a', 'tool_b'],
    ]);
});

test('configures denied tools', function () {
    $server = McpServer::url('https://example.com')
        ->denyTools(['dangerous_tool']);

    $array = $server->toArray();

    expect($array['tool_configuration'])->toBe([
        'enabled' => true,
        'denied_tools' => ['dangerous_tool'],
    ]);
});

test('creates from config array', function () {
    $config = [
        'url' => 'https://zapier.mcp.com/actions',
        'name' => 'zapier',
        'token' => 'zapier-api-key',
        'allowed_tools' => ['gmail_send', 'slack_post'],
    ];

    $server = McpServer::fromConfig('zapier-server', $config);

    $array = $server->toArray();

    expect($array['url'])->toBe('https://zapier.mcp.com/actions');
    expect($array['name'])->toBe('zapier');
    expect($array['authorization_token'])->toBe('zapier-api-key');
    expect($array['tool_configuration']['allowed_tools'])->toBe(['gmail_send', 'slack_post']);
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

    $array = $server->toArray();

    expect($array['tool_configuration']['denied_tools'])->toBe(['file_delete', 'system_exec']);
});

test('fluent interface returns self', function () {
    $server = McpServer::url('https://example.com');

    expect($server->name('test'))->toBe($server);
    expect($server->token('token'))->toBe($server);
    expect($server->allowTools(['tool']))->toBe($server);
    expect($server->denyTools(['tool']))->toBe($server);
});
