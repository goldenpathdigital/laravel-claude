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
        $this->type = $type;

        return $this;
    }

    public function ephemeral(): self
    {
        $this->type = 'ephemeral';

        return $this;
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
