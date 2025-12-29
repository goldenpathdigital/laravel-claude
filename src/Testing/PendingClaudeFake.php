<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Testing;

use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use PHPUnit\Framework\Assert;

class PendingClaudeFake implements ClaudeClientInterface
{
    protected FakeMessagesService $fakeMessages;

    protected array $config;

    public function __construct(array $responses = [], array $config = [])
    {
        $this->config = $config;
        $this->fakeMessages = new FakeMessagesService(
            array_map(fn ($r) => $r instanceof FakeResponse ? $r : FakeResponse::make($r), $responses)
        );
    }

    public function messages(): FakeMessagesService
    {
        return $this->fakeMessages;
    }

    public function conversation(): ConversationBuilder
    {
        return new ConversationBuilder($this);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function recorded(): array
    {
        return $this->fakeMessages->recorded();
    }

    public function assertSent(callable $callback): void
    {
        Assert::assertTrue(
            $this->fakeMessages->assertSent($callback),
            'The expected request was not sent.'
        );
    }

    public function assertNotSent(callable $callback): void
    {
        Assert::assertFalse(
            $this->fakeMessages->assertSent($callback),
            'An unexpected request was sent.'
        );
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertTrue(
            $this->fakeMessages->assertSentCount($count),
            sprintf('Expected %d requests, but %d were sent.', $count, count($this->recorded()))
        );
    }

    public function assertNothingSent(): void
    {
        $this->assertSentCount(0);
    }

    public function assertConversationCount(int $count): void
    {
        $this->assertSentCount($count);
    }
}
