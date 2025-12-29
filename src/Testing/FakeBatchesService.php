<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Testing;

class FakeBatchesService
{
    protected array $responses;

    protected int $index = 0;

    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function create(array $params): object
    {
        return $this->nextResponse() ?? (object) [
            'id' => 'batch_'.uniqid(),
            'type' => 'message_batch',
            'processing_status' => 'in_progress',
        ];
    }

    public function retrieve(string $batchId, array $params): object
    {
        return $this->nextResponse() ?? (object) [
            'id' => $batchId,
            'type' => 'message_batch',
            'processing_status' => 'ended',
        ];
    }

    public function list(array $params): object
    {
        return $this->nextResponse() ?? (object) ['data' => []];
    }

    public function cancel(string $batchId, array $params): object
    {
        return $this->nextResponse() ?? (object) [
            'id' => $batchId,
            'type' => 'message_batch',
            'processing_status' => 'canceling',
        ];
    }

    public function delete(string $batchId, array $params): object
    {
        return $this->nextResponse() ?? (object) [
            'id' => $batchId,
            'type' => 'deleted',
        ];
    }

    public function results(string $batchId, array $params): object
    {
        return $this->nextResponse() ?? (object) ['results' => []];
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
