<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Testing;

class FakeModelsService
{
    protected array $responses;

    protected int $index = 0;

    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function list(array $params): object
    {
        return $this->nextResponse() ?? (object) ['data' => []];
    }

    public function retrieve(string $modelId, array $params): object
    {
        return $this->nextResponse() ?? (object) [
            'id' => $modelId,
            'display_name' => 'Fake Model',
            'type' => 'model',
        ];
    }

    protected function nextResponse(): ?object
    {
        if (empty($this->responses)) {
            return null;
        }

        $response = $this->responses[$this->index] ?? end($this->responses);
        $this->index++;

        return is_object($response) ? $response : (object) $response;
    }
}
