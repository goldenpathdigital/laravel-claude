<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude;

use Anthropic;
use Anthropic\Client;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;

class ClaudeManager
{
    protected Client $client;

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = $this->createClient();
    }

    protected function createClient(): Client
    {
        return Anthropic::client($this->config['api_key']);
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function messages(): \Anthropic\Resources\Messages
    {
        return $this->client->messages();
    }

    public function conversation(): ConversationBuilder
    {
        return new ConversationBuilder($this);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
