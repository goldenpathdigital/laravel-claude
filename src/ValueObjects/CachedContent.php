<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\ValueObjects;

class CachedContent
{
    protected string $content;

    protected string $type = 'ephemeral';

    protected function __construct(string $content)
    {
        $this->content = $content;
    }

    public static function make(string $content): self
    {
        return new self($content);
    }

    public function cache(string $type = 'ephemeral'): self
    {
        $clone = clone $this;
        $clone->type = $type;

        return $clone;
    }

    public function ephemeral(): self
    {
        return $this->cache('ephemeral');
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCacheType(): string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        return [
            'type' => 'text',
            'text' => $this->content,
            'cache_control' => [
                'type' => $this->type,
            ],
        ];
    }
}
