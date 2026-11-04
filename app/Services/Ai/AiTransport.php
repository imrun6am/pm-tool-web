<?php

namespace App\Services\Ai;

/**
 * The wire to the model, kept behind an interface so tests can stand in for it
 * while AiGateway's switches, logging and error handling still run for real.
 */
interface AiTransport
{
    /**
     * Send one structured-output request and return the answering model's JSON
     * text (text before a fallback block is dropped).
     *
     * @param  array{model: string, effort: string, system: string, content: string|array, schema: array, max_tokens: int, timeout: int}  $request
     * @return array{text: string, stop_reason: ?string, model: string, input_tokens: int, output_tokens: int, cache_read_tokens: int}
     */
    public function send(array $request): array;

    /**
     * Send one turn of a tool-use conversation, streaming as it goes. Messages
     * and the returned blocks use the provider-neutral shapes
     * {type: text, text}, {type: tool_use, id, name, input},
     * {type: tool_result, tool_use_id, content, is_error} and thinking blocks,
     * which must be passed back untouched within the turn that made them.
     *
     * @param  array{model: string, effort: string, system: string, messages: list<array>, tools: list<array{name: string, description: string, input_schema: array}>, max_tokens: int, timeout: int}  $request
     * @param  (callable(string, string): void)|null  $onEvent  called with ('text', $delta), or ('reset', '') when a fallback model replaces what came before
     * @return array{blocks: list<array>, stop_reason: ?string, model: string, input_tokens: int, output_tokens: int, cache_read_tokens: int}
     */
    public function converse(array $request, ?callable $onEvent = null): array;
}
