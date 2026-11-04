<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Models\User;
use Throwable;

/**
 * The one door every AI feature goes through. It checks the feature's
 * switch and the API key, sends a structured-output request, turns refusals,
 * truncation and unreadable JSON into an AiException, and logs every call
 * (tokens, time, outcome) to ai_usage_logs for the admin usage page.
 *
 * Callers pass the user's content as data inside tags and say in the system
 * prompt that instructions inside it are text, never instructions.
 */
class AiGateway
{
    public function __construct(
        private readonly AiFeatures $features,
        private readonly ?AiTransport $transport,
    ) {}

    /**
     * @param  string  $feature  a key of config('ai.features')
     * @param  string|array  $content  user-message content (text, or content blocks)
     * @param  array  $schema  JSON schema the answer must follow
     * @return array the decoded JSON answer
     *
     * @throws AiException
     */
    public function json(
        string $feature,
        string $system,
        string|array $content,
        array $schema,
        ?User $user = null,
        int $maxTokens = 16000,
        int $timeout = 90,
    ): array {
        $config = $this->features->get($feature);
        if (! $config['enabled']) {
            throw AiException::disabled($config['label']);
        }
        if ($this->transport === null) {
            throw AiException::notConfigured();
        }

        $started = hrtime(true);
        $log = ['feature' => $feature, 'user_id' => $user?->id, 'model' => $config['model']];

        try {
            $response = $this->transport->send([
                'model' => $config['model'],
                'effort' => $config['effort'],
                'system' => $system,
                'content' => $content,
                'schema' => $schema,
                'max_tokens' => $maxTokens,
                'timeout' => $timeout,
            ]);
        } catch (Throwable $e) {
            $this->record($log + ['status' => 'error', 'error' => $e->getMessage()], $started);
            report($e);

            throw new AiException('The AI couldn’t be reached right now. Please try again.', true, $e);
        }

        $log = [
            'model' => $response['model'] ?: $config['model'],
            'input_tokens' => $response['input_tokens'],
            'output_tokens' => $response['output_tokens'],
            'cache_read_tokens' => $response['cache_read_tokens'],
        ] + $log;

        if ($response['stop_reason'] === 'refusal') {
            $this->record($log + ['status' => 'refused'], $started);

            throw new AiException('The AI declined this request.', false);
        }
        if ($response['stop_reason'] === 'max_tokens') {
            $this->record($log + ['status' => 'truncated'], $started);

            throw new AiException('The AI’s answer was cut off. Try with less content.', false);
        }

        $out = json_decode($response['text'], true);
        if (! is_array($out)) {
            $this->record($log + ['status' => 'invalid', 'error' => 'Unreadable JSON'], $started);

            throw new AiException('The AI returned an unreadable answer. Please try again.');
        }

        $this->record($log + ['status' => 'ok'], $started);

        return $out;
    }

    /**
     * One turn of a tool-use conversation: the same switch, logging and error
     * handling as json(), for a caller that runs its own tool loop.
     *
     * @param  list<array>  $messages  see AiTransport::converse()
     * @param  list<array{name: string, description: string, input_schema: array}>  $tools
     * @param  (callable(string, string): void)|null  $onEvent  see AiTransport::converse()
     * @return array{blocks: list<array>, stop_reason: ?string}
     *
     * @throws AiException
     */
    public function converse(
        string $feature,
        string $system,
        array $messages,
        array $tools,
        ?User $user = null,
        int $maxTokens = 4096,
        int $timeout = 60,
        ?callable $onEvent = null,
    ): array {
        $config = $this->features->get($feature);
        if (! $config['enabled']) {
            throw AiException::disabled($config['label']);
        }
        if ($this->transport === null) {
            throw AiException::notConfigured();
        }

        $started = hrtime(true);
        $log = ['feature' => $feature, 'user_id' => $user?->id, 'model' => $config['model']];

        try {
            $response = $this->transport->converse([
                'model' => $config['model'],
                'effort' => $config['effort'],
                'system' => $system,
                'messages' => $messages,
                'tools' => $tools,
                'max_tokens' => $maxTokens,
                'timeout' => $timeout,
            ], $onEvent);
        } catch (Throwable $e) {
            $this->record($log + ['status' => 'error', 'error' => $e->getMessage()], $started);
            report($e);

            throw new AiException('The AI couldn’t be reached right now. Please try again.', true, $e);
        }

        $log = [
            'model' => $response['model'] ?: $config['model'],
            'input_tokens' => $response['input_tokens'],
            'output_tokens' => $response['output_tokens'],
            'cache_read_tokens' => $response['cache_read_tokens'],
        ] + $log;

        if ($response['stop_reason'] === 'refusal') {
            $this->record($log + ['status' => 'refused'], $started);

            throw new AiException('The AI declined this request.', false);
        }
        if ($response['stop_reason'] === 'max_tokens') {
            $this->record($log + ['status' => 'truncated'], $started);

            throw new AiException('The AI’s answer was cut off. Try asking for less at once.', false);
        }

        $this->record($log + ['status' => 'ok'], $started);

        return ['blocks' => $response['blocks'], 'stop_reason' => $response['stop_reason']];
    }

    private function record(array $attributes, int $started): void
    {
        try {
            AiUsageLog::create($attributes + [
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'error' => isset($attributes['error']) ? mb_strimwidth($attributes['error'], 0, 500, '…') : null,
            ]);
        } catch (Throwable $e) {
            report($e); // usage logging must never break the feature itself
        }
    }
}
