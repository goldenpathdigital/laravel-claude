<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\ClaudeManager;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;

beforeEach(function () {
    $this->manager = new ClaudeManager([
        'api_key' => 'test-api-key',
        'default_model' => 'claude-sonnet-4-5-20250929',
    ]);
    $this->builder = new ConversationBuilder($this->manager);
});

test('image method adds base64 image content block', function () {
    $base64Data = base64_encode('fake-image-data');

    $this->builder->image($base64Data, 'image/jpeg');

    $array = $this->builder->toArray();
    $message = $array['messages'][0];

    expect($message['role'])->toBe('user');
    expect($message['content'][0]['type'])->toBe('image');
    expect($message['content'][0]['source']['type'])->toBe('base64');
    expect($message['content'][0]['source']['media_type'])->toBe('image/jpeg');
    expect($message['content'][0]['source']['data'])->toBe($base64Data);
});

test('image method with text adds both blocks', function () {
    $base64Data = base64_encode('fake-image-data');

    $this->builder->image($base64Data, 'image/png', 'Describe this image');

    $array = $this->builder->toArray();
    $message = $array['messages'][0];

    expect($message['content'])->toHaveCount(2);
    expect($message['content'][0]['type'])->toBe('image');
    expect($message['content'][1]['type'])->toBe('text');
    expect($message['content'][1]['text'])->toBe('Describe this image');
});

test('imageUrl method adds url image content block', function () {
    $url = 'https://example.com/image.jpg';

    $this->builder->imageUrl($url);

    $array = $this->builder->toArray();
    $message = $array['messages'][0];

    expect($message['role'])->toBe('user');
    expect($message['content'][0]['type'])->toBe('image');
    expect($message['content'][0]['source']['type'])->toBe('url');
    expect($message['content'][0]['source']['url'])->toBe($url);
});

test('imageUrl method with text adds both blocks', function () {
    $url = 'https://example.com/image.png';

    $this->builder->imageUrl($url, 'What is in this image?');

    $array = $this->builder->toArray();
    $message = $array['messages'][0];

    expect($message['content'])->toHaveCount(2);
    expect($message['content'][0]['type'])->toBe('image');
    expect($message['content'][1]['type'])->toBe('text');
    expect($message['content'][1]['text'])->toBe('What is in this image?');
});

test('user method accepts array content for complex messages', function () {
    $content = [
        ['type' => 'text', 'text' => 'First part'],
        ['type' => 'image', 'source' => ['type' => 'url', 'url' => 'https://example.com/img.jpg']],
        ['type' => 'text', 'text' => 'Second part'],
    ];

    $this->builder->user($content);

    $array = $this->builder->toArray();
    $message = $array['messages'][0];

    expect($message['role'])->toBe('user');
    expect($message['content'])->toBe($content);
});

test('chained image and user calls build correct message sequence', function () {
    $this->builder
        ->user('Hello')
        ->image(base64_encode('img'), 'image/jpeg', 'Analyze this')
        ->user('Thanks for the analysis');

    $array = $this->builder->toArray();

    expect($array['messages'])->toHaveCount(3);
    expect($array['messages'][0]['content'])->toBe('Hello');
    expect($array['messages'][1]['content'][0]['type'])->toBe('image');
    expect($array['messages'][2]['content'])->toBe('Thanks for the analysis');
});
