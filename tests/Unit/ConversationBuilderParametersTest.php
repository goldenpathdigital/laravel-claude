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

test('stopSequences adds stop sequences to payload', function () {
    $this->builder->stopSequences(['END', 'STOP']);

    $array = $this->builder->toArray();

    expect($array['stop_sequences'])->toBe(['END', 'STOP']);
});

test('topK adds top_k to payload', function () {
    $this->builder->topK(40);

    $array = $this->builder->toArray();

    expect($array['top_k'])->toBe(40);
});

test('topP adds top_p to payload', function () {
    $this->builder->topP(0.9);

    $array = $this->builder->toArray();

    expect($array['top_p'])->toBe(0.9);
});

test('metadata adds metadata to payload', function () {
    $this->builder->metadata(['user_id' => 'user_123']);

    $array = $this->builder->toArray();

    expect($array['metadata'])->toBe(['user_id' => 'user_123']);
});

test('serviceTier adds service_tier to payload', function () {
    $this->builder->serviceTier('auto');

    $array = $this->builder->toArray();

    expect($array['service_tier'])->toBe('auto');
});

test('pdf method adds document content block', function () {
    $pdfData = base64_encode('fake-pdf-data');

    $this->builder->pdf($pdfData);

    $array = $this->builder->toArray();
    $message = $array['messages'][0];

    expect($message['role'])->toBe('user');
    expect($message['content'][0]['type'])->toBe('document');
    expect($message['content'][0]['source']['type'])->toBe('base64');
    expect($message['content'][0]['source']['media_type'])->toBe('application/pdf');
    expect($message['content'][0]['source']['data'])->toBe($pdfData);
});

test('pdf method with text adds both blocks', function () {
    $pdfData = base64_encode('fake-pdf-data');

    $this->builder->pdf($pdfData, 'Summarize this document');

    $array = $this->builder->toArray();
    $message = $array['messages'][0];

    expect($message['content'])->toHaveCount(2);
    expect($message['content'][0]['type'])->toBe('document');
    expect($message['content'][1]['type'])->toBe('text');
    expect($message['content'][1]['text'])->toBe('Summarize this document');
});

test('all parameters can be chained together', function () {
    $this->builder
        ->model('claude-sonnet-4-5-20250929')
        ->temperature(0.7)
        ->topK(40)
        ->topP(0.9)
        ->stopSequences(['END'])
        ->metadata(['user_id' => 'test'])
        ->serviceTier('auto')
        ->maxTokens(2048)
        ->user('Hello');

    $array = $this->builder->toArray();

    expect($array['model'])->toBe('claude-sonnet-4-5-20250929');
    expect($array['temperature'])->toBe(0.7);
    expect($array['top_k'])->toBe(40);
    expect($array['top_p'])->toBe(0.9);
    expect($array['stop_sequences'])->toBe(['END']);
    expect($array['metadata'])->toBe(['user_id' => 'test']);
    expect($array['service_tier'])->toBe('auto');
    expect($array['max_tokens'])->toBe(2048);
});

test('temperature rejects values below 0', function () {
    $this->builder->temperature(-0.1);
})->throws(InvalidArgumentException::class, 'Temperature must be between 0.0 and 1.0');

test('temperature rejects values above 1', function () {
    $this->builder->temperature(1.1);
})->throws(InvalidArgumentException::class, 'Temperature must be between 0.0 and 1.0');

test('temperature accepts boundary values', function () {
    $this->builder->temperature(0.0);
    expect($this->builder->toArray()['temperature'])->toBe(0.0);

    $builder2 = new ConversationBuilder($this->manager);
    $builder2->temperature(1.0);
    expect($builder2->toArray()['temperature'])->toBe(1.0);
});

test('topP rejects values below 0', function () {
    $this->builder->topP(-0.1);
})->throws(InvalidArgumentException::class, 'topP must be between 0.0 and 1.0');

test('topP rejects values above 1', function () {
    $this->builder->topP(1.1);
})->throws(InvalidArgumentException::class, 'topP must be between 0.0 and 1.0');

test('topK rejects zero', function () {
    $this->builder->topK(0);
})->throws(InvalidArgumentException::class, 'topK must be at least 1');

test('topK rejects negative values', function () {
    $this->builder->topK(-5);
})->throws(InvalidArgumentException::class, 'topK must be at least 1');

test('maxTokens rejects zero', function () {
    $this->builder->maxTokens(0);
})->throws(InvalidArgumentException::class, 'maxTokens must be at least 1');

test('maxTokens rejects negative values', function () {
    $this->builder->maxTokens(-100);
})->throws(InvalidArgumentException::class, 'maxTokens must be at least 1');

test('maxSteps rejects zero', function () {
    $this->builder->maxSteps(0);
})->throws(InvalidArgumentException::class, 'maxSteps must be at least 1');

test('maxSteps rejects negative values', function () {
    $this->builder->maxSteps(-1);
})->throws(InvalidArgumentException::class, 'maxSteps must be at least 1');
