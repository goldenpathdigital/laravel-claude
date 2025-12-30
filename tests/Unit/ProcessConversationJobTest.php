<?php

declare(strict_types=1);

use Anthropic\Messages\Message;
use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Contracts\ConversationCallback;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use GoldenPathDigital\Claude\Conversation\PayloadBuilder;
use GoldenPathDigital\Claude\Jobs\ProcessConversation;

beforeEach(function () {
    $this->mockClient = Mockery::mock(ClaudeClientInterface::class);
    $this->mockClient->shouldReceive('config')
        ->with('default_model')
        ->andReturn('claude-sonnet-4-5-20250929');
});

describe('ProcessConversation Job Configuration', function () {
    test('job implements ShouldQueue', function () {
        $conversation = new ConversationBuilder($this->mockClient);
        $conversation->user('Test');

        $job = new ProcessConversation($conversation, JobTestCallback::class);

        expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    test('job has retry configuration', function () {
        $conversation = new ConversationBuilder($this->mockClient);
        $conversation->user('Test');

        $job = new ProcessConversation($conversation, JobTestCallback::class);

        expect($job->tries)->toBe(3);
        expect($job->backoff)->toBe(10);
    });

    test('job uses Dispatchable trait', function () {
        $conversation = new ConversationBuilder($this->mockClient);
        $conversation->user('Test');

        expect(method_exists(ProcessConversation::class, 'dispatch'))->toBeTrue();
    });

    test('job uses InteractsWithQueue trait', function () {
        $conversation = new ConversationBuilder($this->mockClient);
        $conversation->user('Test');

        $job = new ProcessConversation($conversation, JobTestCallback::class);

        expect(method_exists($job, 'attempts'))->toBeTrue();
    });

    test('job serializes all conversation config', function () {
        $conversation = new ConversationBuilder($this->mockClient);
        $conversation
            ->system('System prompt')
            ->user('User message')
            ->maxTokens(2048)
            ->temperature(0.8);

        $job = new ProcessConversation($conversation, JobTestCallback::class);

        $reflection = new ReflectionClass($job);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($job);

        expect($config['system'])->toBe('System prompt');
        expect($config['messages'][0]['content'])->toBe('User message');
        expect($config['max_tokens'])->toBe(2048);
        expect($config['temperature'])->toBe(0.8);
    });

    test('job preserves context through serialization', function () {
        $conversation = new ConversationBuilder($this->mockClient);
        $conversation->user('Test');

        $context = [
            'user_id' => 123,
            'document_id' => 'doc_456',
            'nested' => ['key' => 'value'],
        ];

        $job = new ProcessConversation($conversation, JobTestCallback::class, $context);

        $reflection = new ReflectionClass($job);
        $contextProperty = $reflection->getProperty('context');
        $contextProperty->setAccessible(true);

        expect($contextProperty->getValue($job))->toBe($context);
    });

    test('job stores callback class name', function () {
        $conversation = new ConversationBuilder($this->mockClient);
        $conversation->user('Test');

        $job = new ProcessConversation($conversation, JobTestCallback::class);

        $reflection = new ReflectionClass($job);
        $callbackProperty = $reflection->getProperty('callbackClass');
        $callbackProperty->setAccessible(true);

        expect($callbackProperty->getValue($job))->toBe(JobTestCallback::class);
    });
});

describe('ProcessConversation Payload Building via PayloadBuilder', function () {
    test('PayloadBuilder includes model from config', function () {
        $conversation = new ConversationBuilder($this->mockClient);
        $conversation->user('Test message');

        $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
        $payload = $payloadBuilder->build();

        expect($payload['model'])->toBe('claude-sonnet-4-5-20250929');
        expect($payload['messages'])->toHaveCount(1);
    });

    test('PayloadBuilder includes system when set', function () {
        $conversation = new ConversationBuilder($this->mockClient);
        $conversation
            ->system('You are helpful')
            ->user('Test');

        $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
        $payload = $payloadBuilder->build();

        expect($payload['system'])->toBe('You are helpful');
    });

    test('PayloadBuilder includes temperature when set', function () {
        $conversation = new ConversationBuilder($this->mockClient);
        $conversation
            ->user('Test')
            ->temperature(0.5);

        $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
        $payload = $payloadBuilder->build();

        expect($payload['temperature'])->toBe(0.5);
    });

    test('PayloadBuilder excludes optional fields when not set', function () {
        $conversation = new ConversationBuilder($this->mockClient);
        $conversation->user('Test');

        $payloadBuilder = PayloadBuilder::fromConfig($conversation->toArray());
        $payload = $payloadBuilder->build();

        expect($payload)->not->toHaveKey('temperature');
        expect($payload)->not->toHaveKey('system');
        expect($payload)->not->toHaveKey('stop_sequences');
        expect($payload)->not->toHaveKey('tools');
    });
});

describe('ProcessConversation Callback Resolution', function () {
    test('resolveCallback creates callback instance via app container', function () {
        app()->bind(JobTestCallback::class, fn () => new JobTestCallback);

        $conversation = new ConversationBuilder($this->mockClient);
        $conversation->user('Test');

        $job = new ProcessConversation($conversation, JobTestCallback::class);

        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('resolveCallback');
        $method->setAccessible(true);

        $callback = $method->invoke($job);

        expect($callback)->toBeInstanceOf(JobTestCallback::class);
    });
});

class JobTestCallback implements ConversationCallback
{
    public static ?Message $lastResponse = null;

    public static ?Throwable $lastException = null;

    public static ?array $lastContext = null;

    public function onSuccess(Message $response, array $context = []): void
    {
        self::$lastResponse = $response;
        self::$lastContext = $context;
    }

    public function onFailure(Throwable $exception, array $context = []): void
    {
        self::$lastException = $exception;
        self::$lastContext = $context;
    }
}
