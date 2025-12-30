<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Exceptions;

use InvalidArgumentException;

class ValidationException extends InvalidArgumentException
{
    protected string $field;

    protected mixed $invalidValue;

    public function __construct(string $field, mixed $value, string $message)
    {
        $this->field = $field;
        $this->invalidValue = $value;
        parent::__construct($message);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getInvalidValue(): mixed
    {
        return $this->invalidValue;
    }
}
