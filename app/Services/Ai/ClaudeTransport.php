<?php

namespace App\Services\Ai;

use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaRawContentBlockStartEvent;
use Anthropic\Beta\Messages\BetaRawMessageDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageStartEvent;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Client;

/**
 * Sends AiGateway requests through the Anthropic PHP SDK with structured JSON
 * output and the server-side refusal fallback. Requests with a large output
 * budget are streamed so they never hit an HTTP timeout.
 */
class ClaudeTransport implements AiTransport
{
    /** Above this output budget the request is streamed. */
    private const STREAM_ABOVE = 16000;

    public function __construct(private readonly string $apiKey) {}

    public function send(array $request): array
    {
        $client = new Client(apiKey: $this->apiKey);

        $params = [
            'model' => $request['model'],
            'maxTokens' => $request['max_tokens'],
            'system' => $request['system'],
            'messages' => [['role' => 'user', 'content' => $request['content']]],
            'outputConfig' => [
                'effort' => $request['effort'],
                'format' => ['type' => 'json_schema', 'schema' => $request['schema']],
            ],
            // A policy decline is re-run server-side on the recommended fallback model.
            'fallbacks' => 'default',
            'betas' => ['server-side-fallback-2026-07-01'],
            'requestOptions' => ['timeout' => $request['timeout']],
        ];

        return $request['max_tokens'] > self::STREAM_ABOVE
            ? $this->stream($client, $params, $request['model'])
            : $this->once($client, $params);
    }

    private function once(Client $client, array $params): array
    {
        $message = $client->beta->messages->create(...$params);

        // Text after the last fallback block is the answering model's.
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'fallback') {
                $text = '';
            } elseif ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return [
            'text' => $text,
            'stop_reason' => $message->stopReason,
            'model' => $message->model,
            'input_tokens' => $message->usage->inputTokens,
            'output_tokens' => $message->usage->outputTokens,
            'cache_read_tokens' => (int) $message->usage->cacheReadInputTokens,
        ];
    }

    private function stream(Client $client, array $params, string $model): array
    {
        $text = '';
        $stopReason = null;
        $input = $output = $cacheRead = 0;

        foreach ($client->beta->messages->createStream(...$params) as $event) {
            if ($event instanceof BetaRawContentBlockDeltaEvent && $event->delta instanceof BetaTextDelta) {
                $text .= $event->delta->text;
            } elseif ($event instanceof BetaRawContentBlockStartEvent && $event->contentBlock->type === 'fallback') {
                $text = ''; // the declined model's partial text is superseded
            } elseif ($event instanceof BetaRawMessageStartEvent) {
                $model = $event->message->model;
                $input = $event->message->usage->inputTokens;
                $cacheRead = (int) $event->message->usage->cacheReadInputTokens;
            } elseif ($event instanceof BetaRawMessageDeltaEvent) {
                $stopReason = $event->delta->stopReason;
                $output = $event->usage->outputTokens;
            }
        }

        return [
            'text' => $text,
            'stop_reason' => $stopReason,
            'model' => $model,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cache_read_tokens' => $cacheRead,
        ];
    }
}
