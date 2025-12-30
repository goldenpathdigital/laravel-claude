<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Jobs;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException as SdkAPIException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException as SdkRateLimitException;
use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Contracts\ConversationCallback;
use GoldenPathDigital\Claude\Conversation\ConversationBuilder;
use GoldenPathDigital\Claude\Conversation\PayloadBuilder;
use GoldenPathDigital\Claude\Exceptions\ApiException;
use GoldenPathDigital\Claude\Exceptions\ConfigurationException;
use GoldenPathDigital\Claude\Exceptions\RateLimitException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessConversation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    /** @var array<string, mixed> */
    protected array $config;

    /** @var class-string<ConversationCallback> */
    protected string $callbackClass;

    /** @var array<string, mixed> */
    protected array $context;

    /** @param array<string, mixed> $context */
    public function __construct(
        ConversationBuilder $conversation,
        string $callbackClass,
        array $context = []
    ) {
        $this->config = $conversation->toArray();
        $this->callbackClass = $callbackClass;
        $this->context = $context;
    }

    public function handle(ClaudeClientInterface $client): void
    {
        $payloadBuilder = PayloadBuilder::fromConfig($this->config);
        $payload = $payloadBuilder->build();

        try {
            $response = $client->messages()->create($payload);
        } catch (SdkRateLimitException $e) {
            $retryAfter = $e->response?->getHeader('retry-after')[0] ?? null;
            throw new RateLimitException(
                'Rate limit exceeded. Please retry later.',
                $retryAfter !== null ? (int) $retryAfter : null,
                $e
            );
        } catch (AuthenticationException $e) {
            throw new ConfigurationException(
                'Invalid API credentials. Check your ANTHROPIC_API_KEY or ANTHROPIC_AUTH_TOKEN.',
                0,
                $e
            );
        } catch (APIConnectionException $e) {
            throw new ApiException(
                'Failed to connect to Claude API: '.$e->getMessage(),
                'connection_error',
                $e
            );
        } catch (SdkAPIException $e) {
            throw new ApiException(
                $e->getMessage(),
                $e->body['error']['type'] ?? null,
                $e
            );
        }

        $callback = $this->resolveCallback();
        $callback->onSuccess($response, $this->context);
    }

    public function failed(Throwable $exception): void
    {
        $callback = $this->resolveCallback();
        $callback->onFailure($exception, $this->context);
    }

    protected function resolveCallback(): ConversationCallback
    {
        return app($this->callbackClass);
    }
}
