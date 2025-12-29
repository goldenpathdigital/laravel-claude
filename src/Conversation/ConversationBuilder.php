<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Conversation;

use GoldenPathDigital\Claude\ClaudeManager;

class ConversationBuilder
{
    protected ClaudeManager $manager;

    protected ?string $model = null;

    protected ?string $system = null;

    protected array $messages = [];

    protected int $maxTokens = 1024;

    protected ?float $temperature = null;

    public function __construct(ClaudeManager $manager)
    {
        $this->manager = $manager;
        $this->model = $manager->config('default_model');
    }

    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function system(string $system): self
    {
        $this->system = $system;

        return $this;
    }

    public function user(string $content): self
    {
        $this->messages[] = [
            'role' => 'user',
            'content' => $content,
        ];

        return $this;
    }

    public function assistant(string $content): self
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $content,
        ];

        return $this;
    }

    public function maxTokens(int $tokens): self
    {
        $this->maxTokens = $tokens;

        return $this;
    }

    public function temperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function send(): \Anthropic\Responses\Messages\CreateResponse
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $this->messages,
        ];

        if ($this->system !== null) {
            $payload['system'] = $this->system;
        }

        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }

        $response = $this->manager->messages()->create($payload);

        $this->appendAssistantResponse($response);

        return $response;
    }

    protected function appendAssistantResponse(\Anthropic\Responses\Messages\CreateResponse $response): void
    {
        if (! empty($response->content)) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $response->content[0]->text ?? '',
            ];
        }
    }

    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'system' => $this->system,
            'messages' => $this->messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];
    }
}
