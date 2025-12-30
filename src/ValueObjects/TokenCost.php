<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\ValueObjects;

final class TokenCost
{
    public function __construct(
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly float $inputCost,
        public readonly float $outputCost,
        public readonly string $model,
    ) {}

    public function total(): float
    {
        return $this->inputCost + $this->outputCost;
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    public function formatted(string $currency = '$', int $decimals = 6): string
    {
        return $currency.number_format($this->total(), $decimals);
    }

    /** @param array{input: float, output: float} $pricing */
    public static function calculate(
        int $inputTokens,
        int $outputTokens,
        array $pricing,
        string $model = 'unknown',
    ): self {
        $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];

        return new self(
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            inputCost: $inputCost,
            outputCost: $outputCost,
            model: $model,
        );
    }

    /** @param array{input: float, output: float} $pricing */
    public static function forInput(
        int $inputTokens,
        array $pricing,
        string $model = 'unknown',
    ): self {
        return self::calculate($inputTokens, 0, $pricing, $model);
    }

    /**
     * @return array{
     *     model: string,
     *     input_tokens: int,
     *     output_tokens: int,
     *     input_cost: float,
     *     output_cost: float,
     *     total_cost: float,
     *     total_tokens: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'input_cost' => $this->inputCost,
            'output_cost' => $this->outputCost,
            'total_cost' => $this->total(),
            'total_tokens' => $this->totalTokens(),
        ];
    }
}
