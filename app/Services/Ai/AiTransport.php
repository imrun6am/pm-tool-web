<?php

namespace App\Services\Ai;

/**
 * The wire to Claude, kept behind an interface so tests can stand in for it
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
}
