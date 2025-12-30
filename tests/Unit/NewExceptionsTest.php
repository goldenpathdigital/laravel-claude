<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\Exceptions\ApiException;
use GoldenPathDigital\Claude\Exceptions\RateLimitException;
use GoldenPathDigital\Claude\Exceptions\ToolExecutionException;
use GoldenPathDigital\Claude\Exceptions\ValidationException;

describe('ToolExecutionException', function () {
    test('stores tool name and input', function () {
        $exception = new ToolExecutionException(
            'my_tool',
            ['param' => 'value'],
            'Tool failed'
        );

        expect($exception->getToolName())->toBe('my_tool');
        expect($exception->getToolInput())->toBe(['param' => 'value']);
        expect($exception->getMessage())->toBe('Tool failed');
    });

    test('wraps previous exception', function () {
        $original = new RuntimeException('Original error');
        $exception = new ToolExecutionException(
            'my_tool',
            [],
            'Wrapped error',
            $original
        );

        expect($exception->getPrevious())->toBe($original);
    });
});

describe('ValidationException', function () {
    test('stores field and invalid value', function () {
        $exception = new ValidationException(
            'temperature',
            1.5,
            'Temperature must be between 0 and 1'
        );

        expect($exception->getField())->toBe('temperature');
        expect($exception->getInvalidValue())->toBe(1.5);
        expect($exception->getMessage())->toBe('Temperature must be between 0 and 1');
    });

    test('handles null invalid value', function () {
        $exception = new ValidationException('required_field', null, 'Field is required');

        expect($exception->getInvalidValue())->toBeNull();
    });
});

describe('ApiException', function () {
    test('stores api error type', function () {
        $exception = new ApiException(
            'Invalid request',
            'invalid_request_error'
        );

        expect($exception->getApiErrorType())->toBe('invalid_request_error');
        expect($exception->getMessage())->toBe('Invalid request');
    });

    test('handles null error type', function () {
        $exception = new ApiException('Unknown error');

        expect($exception->getApiErrorType())->toBeNull();
    });

    test('wraps previous exception', function () {
        $original = new RuntimeException('Original API error');
        $exception = new ApiException('Wrapped', 'error_type', $original);

        expect($exception->getPrevious())->toBe($original);
    });
});

describe('RateLimitException', function () {
    test('extends ApiException', function () {
        $exception = new RateLimitException;

        expect($exception)->toBeInstanceOf(ApiException::class);
    });

    test('has default message', function () {
        $exception = new RateLimitException;

        expect($exception->getMessage())->toBe('Rate limit exceeded');
        expect($exception->getApiErrorType())->toBe('rate_limit_error');
    });

    test('stores retry after seconds', function () {
        $exception = new RateLimitException('Please wait', 30);

        expect($exception->getRetryAfterSeconds())->toBe(30);
    });

    test('handles null retry after', function () {
        $exception = new RateLimitException;

        expect($exception->getRetryAfterSeconds())->toBeNull();
    });
});
