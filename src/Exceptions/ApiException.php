<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Exceptions;

use RuntimeException;
use Throwable;

class ApiException extends RuntimeException
{
    protected ?string $apiErrorType;

    public function __construct(
        string $message,
        ?string $apiErrorType = null,
        ?Throwable $previous = null
    ) {
        $this->apiErrorType = $apiErrorType;
        parent::__construct($message, 0, $previous);
    }

    public function getApiErrorType(): ?string
    {
        return $this->apiErrorType;
    }
}
