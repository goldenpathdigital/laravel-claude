<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Testing;

use Anthropic\Messages\Message;

class FakeMessagesService
{
    protected array $responses = [];

    protected int $responseIndex = 0;

    protected array $recorded = [];

    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function addResponse(FakeResponse $response): void
    {
        $this->responses[] = $response;
    }

    public function create(array $params): Message
    {
        $this->recorded[] = $params;

        if (empty($this->responses)) {
            return FakeResponse::make('Fake response')->toMessage();
        }

        $response = $this->responses[$this->responseIndex] ?? end($this->responses);

        if ($this->responseIndex < count($this->responses) - 1) {
            $this->responseIndex++;
        }

        return $response->toMessage();
    }

    public function createStream(array $params): iterable
    {
        $this->recorded[] = $params;

        return [];
    }

    public function recorded(): array
    {
        return $this->recorded;
    }

    public function assertSent(callable $callback): bool
    {
        foreach ($this->recorded as $request) {
            if ($callback($request)) {
                return true;
            }
        }

        return false;
    }

    public function assertSentCount(int $count): bool
    {
        return count($this->recorded) === $count;
    }
}
