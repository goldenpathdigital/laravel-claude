<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Testing;

class FakeFilesService
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

    public function delete(string $fileId, array $params): object
    {
        return $this->nextResponse() ?? (object) [
            'id' => $fileId,
            'type' => 'deleted',
        ];
    }

    public function retrieveMetadata(string $fileId, array $params): object
    {
        return $this->nextResponse() ?? (object) [
            'id' => $fileId,
            'filename' => 'fake-file.pdf',
            'type' => 'file',
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
