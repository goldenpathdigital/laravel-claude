<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\Exceptions\ValidationException;
use GoldenPathDigital\Claude\MCP\McpServer;

test('mcp server requires url', function () {
    McpServer::url('');
})->throws(ValidationException::class, 'MCP server URL cannot be empty');

test('mcp server requires name before toArray', function () {
    $server = McpServer::url('https://mcp.example.com');
    $server->toArray();
})->throws(ValidationException::class, 'MCP server must have a name');

test('mcp server requires name before toToolsetArray', function () {
    $server = McpServer::url('https://mcp.example.com');
    $server->toToolsetArray();
})->throws(ValidationException::class, 'MCP server must have a name');

test('mcp server name cannot be empty', function () {
    $server = McpServer::url('https://mcp.example.com');
    $server->name('');
})->throws(ValidationException::class, 'MCP server name cannot be empty');

test('mcp server name cannot be whitespace only', function () {
    $server = McpServer::url('https://mcp.example.com');
    $server->name('   ');
})->throws(ValidationException::class, 'MCP server name cannot be empty');

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
})->throws(ValidationException::class, "MCP server 'test' has no URL configured");

test('fromConfig uses key as name if not provided', function () {
    $server = McpServer::fromConfig('my-server', [
        'url' => 'https://mcp.example.com',
    ]);

    expect($server->getName())->toBe('my-server');
});

test('rejects invalid url format', function () {
    McpServer::url('not-a-valid-url');
})->throws(ValidationException::class, 'Invalid MCP server URL format');

test('rejects localhost url', function () {
    McpServer::url('http://localhost/api');
})->throws(ValidationException::class, 'MCP server URL cannot target local or internal hosts');

test('rejects 127.0.0.1 url', function () {
    McpServer::url('http://127.0.0.1:8080/api');
})->throws(ValidationException::class, 'MCP server URL cannot target local or internal hosts');

test('rejects 0.0.0.0 url', function () {
    McpServer::url('http://0.0.0.0/api');
})->throws(ValidationException::class, 'MCP server URL cannot target local or internal hosts');

test('rejects aws metadata endpoint', function () {
    McpServer::url('http://169.254.169.254/latest/meta-data');
})->throws(ValidationException::class, 'MCP server URL cannot target local or internal hosts');

test('rejects private ip 10.x.x.x', function () {
    McpServer::url('http://10.0.0.1/api');
})->throws(ValidationException::class, 'MCP server URL cannot target private IP addresses');

test('rejects private ip 172.16.x.x', function () {
    McpServer::url('http://172.16.0.1/api');
})->throws(ValidationException::class, 'MCP server URL cannot target private IP addresses');

test('rejects private ip 192.168.x.x', function () {
    McpServer::url('http://192.168.1.1/api');
})->throws(ValidationException::class, 'MCP server URL cannot target private IP addresses');

test('rejects link-local ip 169.254.x.x', function () {
    McpServer::url('http://169.254.1.1/api');
})->throws(ValidationException::class, 'MCP server URL cannot target private IP addresses');

test('accepts valid https url', function () {
    $server = McpServer::url('https://api.example.com/mcp')->name('test');
    expect($server->toArray()['url'])->toBe('https://api.example.com/mcp');
});

test('accepts valid http url', function () {
    $server = McpServer::url('http://api.example.com/mcp')->name('test');
    expect($server->toArray()['url'])->toBe('http://api.example.com/mcp');
});

test('accepts public ip address', function () {
    $server = McpServer::url('https://8.8.8.8/api')->name('test');
    expect($server->toArray()['url'])->toBe('https://8.8.8.8/api');
});

test('rejects non-http protocol', function () {
    McpServer::url('ftp://example.com/file');
})->throws(ValidationException::class, 'MCP server URL must use HTTP or HTTPS protocol');
