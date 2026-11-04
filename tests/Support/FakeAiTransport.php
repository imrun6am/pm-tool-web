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

    /** @var list<array> */
    private array $turns = [];

    private int $nextToolId = 0;

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

    /** Queue a conversation turn that says something and stops. */
    public function say(string $text): static
    {
        $this->turns[] = ['blocks' => [['type' => 'text', 'text' => $text]], 'stop' => 'end_turn'];

        return $this;
    }

    /** Queue a conversation turn that calls tools: pairs of [name, input], with optional text first. */
    public function callTools(array $calls, ?string $text = null): static
    {
        $blocks = $text === null ? [] : [['type' => 'text', 'text' => $text]];
        foreach ($calls as $i => [$name, $input]) {
            $blocks[] = ['type' => 'tool_use', 'id' => 'toolu_'.(++$this->nextToolId), 'name' => $name, 'input' => $input];
        }
        $this->turns[] = ['blocks' => $blocks, 'stop' => 'tool_use'];

        return $this;
    }

    /** Queue a conversation turn with raw blocks and a stop reason. */
    public function turn(array $blocks, ?string $stopReason = 'end_turn'): static
    {
        $this->turns[] = ['blocks' => $blocks, 'stop' => $stopReason];

        return $this;
    }

    /** Forget conversation turns that weren't used. */
    public function clearTurns(): static
    {
        $this->turns = [];

        return $this;
    }

    /** Stand in for an outage on the next conversation turn. */
    public function failNextTurn(): static
    {
        $this->turns[] = ['fail' => true];

        return $this;
    }

    public function converse(array $request, ?callable $onEvent = null): array
    {
        $this->requests[] = $request;

        $next = array_shift($this->turns) ?? throw new RuntimeException('FakeAiTransport: no conversation turn queued.');
        if ($next['fail'] ?? false) {
            throw new RuntimeException('FakeAiTransport: outage.');
        }

        foreach ($next['blocks'] as $block) {
            if ($block['type'] === 'text' && $onEvent) {
                $onEvent('text', $block['text']);
            }
        }

        return [
            'blocks' => $next['blocks'],
            'stop_reason' => $next['stop'],
            'model' => $request['model'],
            'input_tokens' => 1200,
            'output_tokens' => 300,
            'cache_read_tokens' => 0,
        ];
    }

    /** What the nth request showed the model, tool results included, as one string. */
    public function sentToModel(int $n = -1): string
    {
        $request = $n < 0 ? end($this->requests) : $this->requests[$n];

        return json_encode($request['messages'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** The user-message text of the nth request (content blocks joined). */
    public function prompt(int $n = 0): string
    {
        $content = $this->requests[$n]['content'] ?? '';

        return is_string($content) ? $content : collect($content)->pluck('text')->filter()->implode("\n");
    }
}
