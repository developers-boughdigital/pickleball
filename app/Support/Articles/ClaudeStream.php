<?php

namespace App\Support\Articles;

use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageDeltaEvent;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Client;
use Generator;

/**
 * One streamed request to Claude. Kept apart from DraftWriter so tests can
 * swap it for canned text and never call the API.
 */
class ClaudeStream
{
    /** Why Claude stopped: end_turn, max_tokens, refusal… Set once the stream is read to the end. */
    public ?string $stopReason = null;

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Send the prompt and yield the reply as it is written, a few words at a time.
     *
     * @return Generator<int, string>
     */
    public function stream(string $system, string $prompt): Generator
    {
        $client = new Client(apiKey: config('services.anthropic.key'));

        // Streaming, because a full article takes a couple of minutes and a plain
        // request that long gets cut off by timeouts along the way.
        $stream = $client->beta->messages->createStream(
            maxTokens: 32000,
            messages: [['role' => 'user', 'content' => $prompt]],
            model: config('services.anthropic.model'),
            system: $system,
            thinking: ['type' => 'adaptive'],
            outputConfig: ['effort' => 'high'],
            // If the model declines a request, Anthropic retries it once on a fallback model.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        foreach ($stream as $event) {
            if ($event instanceof BetaRawContentBlockDeltaEvent && $event->delta instanceof BetaTextDelta) {
                yield $event->delta->text;
            } elseif ($event instanceof BetaRawMessageDeltaEvent) {
                $this->stopReason = $event->delta->stopReason;
            }
        }
    }
}
