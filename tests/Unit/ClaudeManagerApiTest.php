<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Services\Beta\FilesService;
use Anthropic\Services\Beta\Messages\BatchesService;
use Anthropic\Services\MessagesService;
use Anthropic\Services\ModelsService;
use GoldenPathDigital\Claude\ClaudeManager;

beforeEach(function () {
    $this->manager = new ClaudeManager([
        'api_key' => 'test-api-key',
        'default_model' => 'claude-sonnet-4-5-20250929',
    ]);
});

test('manager exposes messages service', function () {
    expect($this->manager->messages())->toBeInstanceOf(MessagesService::class);
});

test('manager exposes models service', function () {
    expect($this->manager->models())->toBeInstanceOf(ModelsService::class);
});

test('manager exposes batches service', function () {
    expect($this->manager->batches())->toBeInstanceOf(BatchesService::class);
});

test('manager exposes files service', function () {
    expect($this->manager->files())->toBeInstanceOf(FilesService::class);
});

test('manager exposes underlying client', function () {
    expect($this->manager->client())->toBeInstanceOf(Client::class);
});
