<?php

namespace App\Services\Ai;

use Anthropic\Beta\Messages\BetaInputJSONDelta;
use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaRawContentBlockStartEvent;
use Anthropic\Beta\Messages\BetaRawMessageDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageStartEvent;
use Anthropic\Beta\Messages\BetaSignatureDelta;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Beta\Messages\BetaThinkingDelta;
use Anthropic\Client;

/**
 * Sends AiGateway requests through the Anthropic PHP SDK with structured JSON
 * output and the server-side refusal fallback. Requests with a large output
 * budget are streamed so they never hit an HTTP timeout. Tool-use conversations
 * (converse) are always streamed, so a chat shows its answer as it is written.
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
            'requestOptions' => ['timeout' => $request['timeout']],
        ];

        // A policy decline is re-run server-side on the recommended fallback
        // model — on the models that offer it (not Haiku).
        if (! in_array($request['model'], config('ai.no_fallback', []), true)) {
            $params['fallbacks'] = 'default';
            $params['betas'] = ['server-side-fallback-2026-07-01'];
        }

        return $request['max_tokens'] > self::STREAM_ABOVE
            ? $this->stream($client, $params, $request['model'])
            : $this->once($client, $params);
    }

    public function converse(array $request, ?callable $onEvent = null): array
    {
        $client = new Client(apiKey: $this->apiKey);

        $params = [
            'model' => $request['model'],
            'maxTokens' => $request['max_tokens'],
            // The tools and system prompt never change, so they cache; the
            // top-level marker also caches the history for the next round.
            'system' => [['type' => 'text', 'text' => $request['system'], 'cacheControl' => ['type' => 'ephemeral']]],
            'cacheControl' => ['type' => 'ephemeral'],
            'messages' => array_map($this->toSdkMessage(...), $request['messages']),
            'tools' => array_map(fn (array $tool) => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'inputSchema' => $tool['input_schema'],
            ], $request['tools']),
            'outputConfig' => ['effort' => $request['effort']],
            'requestOptions' => ['timeout' => $request['timeout']],
        ];

        if (! in_array($request['model'], config('ai.no_fallback', []), true)) {
            $params['fallbacks'] = 'default';
            $params['betas'] = ['server-side-fallback-2026-07-01'];
        }

        $blocks = [];
        $json = [];
        $stopReason = null;
        $model = $request['model'];
        $input = $output = $cacheRead = 0;
        $emit = $onEvent ?? fn () => null;

        foreach ($client->beta->messages->createStream(...$params) as $event) {
            if ($event instanceof BetaRawContentBlockStartEvent) {
                $block = $event->contentBlock;
                match ($block->type) {
                    'text' => $blocks[$event->index] = ['type' => 'text', 'text' => ''],
                    'thinking' => $blocks[$event->index] = ['type' => 'thinking', 'thinking' => '', 'signature' => ''],
                    'redacted_thinking' => $blocks[$event->index] = ['type' => 'redacted_thinking', 'data' => $block->data],
                    'tool_use' => $blocks[$event->index] = ['type' => 'tool_use', 'id' => $block->id, 'name' => $block->name, 'input' => []],
                    default => null,
                };
                if ($block->type === 'fallback') {
                    // What the declined model wrote is superseded.
                    $blocks = [];
                    $json = [];
                    $emit('reset', '');
                }
            } elseif ($event instanceof BetaRawContentBlockDeltaEvent) {
                $delta = $event->delta;
                if ($delta instanceof BetaTextDelta) {
                    $blocks[$event->index]['text'] .= $delta->text;
                    $emit('text', $delta->text);
                } elseif ($delta instanceof BetaInputJSONDelta) {
                    $json[$event->index] = ($json[$event->index] ?? '').$delta->partialJSON;
                } elseif ($delta instanceof BetaThinkingDelta) {
                    $blocks[$event->index]['thinking'] .= $delta->thinking;
                } elseif ($delta instanceof BetaSignatureDelta) {
                    $blocks[$event->index]['signature'] .= $delta->signature;
                }
            } elseif ($event instanceof BetaRawMessageStartEvent) {
                $model = $event->message->model;
                $input = $event->message->usage->inputTokens;
                $cacheRead = (int) $event->message->usage->cacheReadInputTokens;
            } elseif ($event instanceof BetaRawMessageDeltaEvent) {
                $stopReason = $event->delta->stopReason;
                $output = $event->usage->outputTokens;
            }
        }

        foreach ($json as $index => $text) {
            if (isset($blocks[$index])) {
                $blocks[$index]['input'] = (array) (json_decode($text, true) ?: []);
            }
        }

        return [
            'blocks' => array_values($blocks),
            'stop_reason' => $stopReason,
            'model' => $model,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cache_read_tokens' => $cacheRead,
        ];
    }

    /** The SDK takes camelCase keys and maps them to the wire names. */
    private function toSdkMessage(array $message): array
    {
        return [
            'role' => $message['role'],
            'content' => array_map(fn (array $block) => match ($block['type']) {
                'tool_use' => ['type' => 'tool_use', 'id' => $block['id'], 'name' => $block['name'], 'input' => (object) $block['input']],
                'tool_result' => [
                    'type' => 'tool_result',
                    'toolUseID' => $block['tool_use_id'],
                    'content' => $block['content'],
                    'isError' => (bool) ($block['is_error'] ?? false),
                ],
                default => $block,
            }, $message['content']),
        ];
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
