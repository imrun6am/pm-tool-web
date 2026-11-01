<?php

namespace Tests\Support;

use App\Services\Ai\AiTransport;
use Closure;
use RuntimeException;

/**
 * Stands in for Claude in tests. Queue answers with respond() (an array is
 * JSON-encoded) or answer every call with respondUsing(); every request is
 * recorded so tests can assert what was sent.
 */
class FakeAiTransport implements AiTransport
{
    /** @var list<array> */
    public array $requests = [];

    /** @var list<array|Closure|string> */
    private array $queue = [];

    private ?Closure $using = null;

    public function respond(array|string $answer, ?string $stopReason = 'end_turn'): static
    {
        $this->queue[] = ['answer' => $answer, 'stop' => $stopReason];

        return $this;
    }

    public function respondUsing(Closure $using): static
    {
        $this->using = $using;

        return $this;
    }

    public function send(array $request): array
    {
        $this->requests[] = $request;

        if ($this->using) {
            $answer = ($this->using)($request);
            $stop = 'end_turn';
        } elseif ($next = array_shift($this->queue)) {
            ['answer' => $answer, 'stop' => $stop] = $next;
        } else {
            throw new RuntimeException('FakeAiTransport: no answer queued.');
        }

        return [
            'text' => is_array($answer) ? json_encode($answer) : $answer,
            'stop_reason' => $stop,
            'model' => $request['model'],
            'input_tokens' => 1200,
            'output_tokens' => 300,
            'cache_read_tokens' => 0,
        ];
    }

    /** The user-message text of the nth request (content blocks joined). */
    public function prompt(int $n = 0): string
    {
        $content = $this->requests[$n]['content'] ?? '';

        return is_string($content) ? $content : collect($content)->pluck('text')->filter()->implode("\n");
    }
}
