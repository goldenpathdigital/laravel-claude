<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\MCP\McpServer;

test('mcp server requires url', function () {
    McpServer::url('');
})->throws(InvalidArgumentException::class, 'MCP server URL cannot be empty');

test('mcp server requires name before toArray', function () {
    $server = McpServer::url('https://mcp.example.com');
    $server->toArray();
})->throws(InvalidArgumentException::class, 'MCP server must have a name');

test('mcp server requires name before toToolsetArray', function () {
    $server = McpServer::url('https://mcp.example.com');
    $server->toToolsetArray();
})->throws(InvalidArgumentException::class, 'MCP server must have a name');

test('mcp server name cannot be empty', function () {
    $server = McpServer::url('https://mcp.example.com');
    $server->name('');
})->throws(InvalidArgumentException::class, 'MCP server name cannot be empty');

test('mcp server name cannot be whitespace only', function () {
    $server = McpServer::url('https://mcp.example.com');
    $server->name('   ');
})->throws(InvalidArgumentException::class, 'MCP server name cannot be empty');

test('mcp server with valid name works', function () {
    $server = McpServer::url('https://mcp.example.com')
        ->name('my-server')
        ->token('secret');

    $array = $server->toArray();

    expect($array['name'])->toBe('my-server');
    expect($array['url'])->toBe('https://mcp.example.com');
    expect($array['authorization_token'])->toBe('secret');
});

test('fromConfig validates url', function () {
    McpServer::fromConfig('test', ['url' => '']);
})->throws(InvalidArgumentException::class, "MCP server 'test' has no URL configured");

test('fromConfig uses key as name if not provided', function () {
    $server = McpServer::fromConfig('my-server', [
        'url' => 'https://mcp.example.com',
    ]);

    expect($server->getName())->toBe('my-server');
});
