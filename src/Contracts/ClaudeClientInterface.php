<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Contracts;

use Anthropic\Beta\Messages\BetaMessageTokensCount;
use Anthropic\Services\Beta\FilesService;
use Anthropic\Services\Beta\Messages\BatchesService;
use Anthropic\Services\MessagesService;
use Anthropic\Services\ModelsService;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use GoldenPathDigital\Claude\Testing\FakeBatchesService;
use GoldenPathDigital\Claude\Testing\FakeFilesService;
use GoldenPathDigital\Claude\Testing\FakeMessagesService;
use GoldenPathDigital\Claude\Testing\FakeModelsService;

interface ClaudeClientInterface
{
    public function messages(): MessagesService|FakeMessagesService;

    public function models(): ModelsService|FakeModelsService;

    public function batches(): BatchesService|FakeBatchesService;

    public function files(): FilesService|FakeFilesService;

    public function countTokens(array $params): BetaMessageTokensCount;

    public function conversation(): ConversationBuilder;

    public function config(string $key, mixed $default = null): mixed;
}
