<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\Tools\Tool;

test('creates tool with name', function () {
    $tool = Tool::make('get_weather');

    expect($tool->getName())->toBe('get_weather');
});

test('sets description', function () {
    $tool = Tool::make('get_weather')
        ->description('Get current weather for a location');

    $array = $tool->toArray();

    expect($array['description'])->toBe('Get current weather for a location');
});

test('adds required parameter', function () {
    $tool = Tool::make('get_weather')
        ->parameter('location', 'string', 'City name', required: true);

    $array = $tool->toArray();

    expect($array['input_schema']['properties']['location'])->toBe([
        'type' => 'string',
        'description' => 'City name',
    ]);
    expect($array['input_schema']['required'])->toContain('location');
});

test('adds optional parameter', function () {
    $tool = Tool::make('get_weather')
        ->parameter('units', 'string', 'Temperature units', required: false, default: 'celsius');

    $array = $tool->toArray();

    expect($array['input_schema']['properties']['units'])->toBe([
        'type' => 'string',
        'description' => 'Temperature units',
        'default' => 'celsius',
    ]);
    expect($array['input_schema']['required'] ?? [])->not->toContain('units');
});

test('adds parameter with enum', function () {
    $tool = Tool::make('get_weather')
        ->parameter('units', 'string', 'Units', enum: ['celsius', 'fahrenheit']);

    $array = $tool->toArray();

    expect($array['input_schema']['properties']['units']['enum'])->toBe(['celsius', 'fahrenheit']);
});

test('executes handler with input', function () {
    $tool = Tool::make('add_numbers')
        ->parameter('a', 'number', 'First number', required: true)
        ->parameter('b', 'number', 'Second number', required: true)
        ->handler(fn (array $input) => $input['a'] + $input['b']);

    $result = $tool->execute(['a' => 5, 'b' => 3]);

    expect($result)->toBe(8);
});

test('throws exception when executing without handler', function () {
    $tool = Tool::make('no_handler');

    $tool->execute(['test' => 'input']);
})->throws(RuntimeException::class, "No handler defined for tool 'no_handler'");

test('reports handler presence', function () {
    $toolWithHandler = Tool::make('with_handler')
        ->handler(fn ($input) => 'result');

    $toolWithoutHandler = Tool::make('without_handler');

    expect($toolWithHandler->hasHandler())->toBeTrue();
    expect($toolWithoutHandler->hasHandler())->toBeFalse();
});

test('converts to array format', function () {
    $tool = Tool::make('search')
        ->description('Search the web')
        ->parameter('query', 'string', 'Search query', required: true)
        ->parameter('limit', 'integer', 'Max results', default: 10);

    $array = $tool->toArray();

    expect($array)->toHaveKeys(['name', 'description', 'input_schema']);
    expect($array['name'])->toBe('search');
    expect($array['input_schema']['type'])->toBe('object');
    expect($array['input_schema']['properties'])->toHaveKeys(['query', 'limit']);
    expect($array['input_schema']['required'])->toBe(['query']);
});

test('converts to SDK tool', function () {
    $tool = Tool::make('calculator')
        ->description('Perform calculations')
        ->parameter('expression', 'string', 'Math expression', required: true);

    $sdkTool = $tool->toSdkTool();

    expect($sdkTool)->toBeInstanceOf(\Anthropic\Messages\Tool::class);
});

test('handles empty parameters', function () {
    $tool = Tool::make('simple_tool')
        ->description('A tool with no parameters');

    $array = $tool->toArray();

    expect($array['input_schema']['properties'])->toBeInstanceOf(stdClass::class);
    expect($array['input_schema']['required'] ?? null)->toBeNull();
});
