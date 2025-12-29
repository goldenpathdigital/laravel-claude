<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\Testing\FakeBatchesService;
use GoldenPathDigital\Claude\Testing\FakeFilesService;
use GoldenPathDigital\Claude\Testing\FakeModelsService;
use GoldenPathDigital\Claude\Testing\PendingClaudeFake;

test('fake models service returns default response', function () {
    $fake = new FakeModelsService;

    $result = $fake->list([]);

    expect($result->data)->toBe([]);
});

test('fake models service retrieve returns model info', function () {
    $fake = new FakeModelsService;

    $result = $fake->retrieve('claude-sonnet-4-5-20250929', []);

    expect($result->id)->toBe('claude-sonnet-4-5-20250929');
    expect($result->display_name)->toBe('Fake Model');
});

test('fake batches service create returns batch', function () {
    $fake = new FakeBatchesService;

    $result = $fake->create(['requests' => []]);

    expect($result->type)->toBe('message_batch');
    expect($result->processing_status)->toBe('in_progress');
});

test('fake batches service retrieve returns batch', function () {
    $fake = new FakeBatchesService;

    $result = $fake->retrieve('batch_123', []);

    expect($result->id)->toBe('batch_123');
    expect($result->processing_status)->toBe('ended');
});

test('fake files service list returns empty data', function () {
    $fake = new FakeFilesService;

    $result = $fake->list([]);

    expect($result->data)->toBe([]);
});

test('fake files service retrieve metadata returns file info', function () {
    $fake = new FakeFilesService;

    $result = $fake->retrieveMetadata('file_123', []);

    expect($result->id)->toBe('file_123');
    expect($result->filename)->toBe('fake-file.pdf');
});

test('pending claude fake exposes models service', function () {
    $fake = new PendingClaudeFake;

    expect($fake->models())->toBeInstanceOf(FakeModelsService::class);
});

test('pending claude fake exposes batches service', function () {
    $fake = new PendingClaudeFake;

    expect($fake->batches())->toBeInstanceOf(FakeBatchesService::class);
});

test('pending claude fake exposes files service', function () {
    $fake = new PendingClaudeFake;

    expect($fake->files())->toBeInstanceOf(FakeFilesService::class);
});

test('pending claude fake count tokens returns default', function () {
    $fake = new PendingClaudeFake;

    $result = $fake->countTokens(['messages' => []]);

    expect($result->input_tokens)->toBe(10);
});

test('pending claude fake can set custom token count', function () {
    $fake = new PendingClaudeFake;
    $fake->fakeTokenCount(500);

    $result = $fake->countTokens(['messages' => []]);

    expect($result->input_tokens)->toBe(500);
});
