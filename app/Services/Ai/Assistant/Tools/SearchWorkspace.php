<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Models\User;
use App\Services\Ai\Assistant\ReadTool;
use App\Services\Ai\Assistant\ToolOutput;
use App\Services\Ai\WorkspaceAsk;

/** The same retrieval as Ask AI, so both see the same records through the same scopes. */
class SearchWorkspace extends ReadTool
{
    private const MAX_CHARS = 14000;

    public function __construct(private readonly WorkspaceAsk $ask) {}

    public function name(): string
    {
        return 'search_workspace';
    }

    public function description(): string
    {
        return 'Keyword search across the releases, tasks, meeting notes, daily notes, events, teams and projects this person may open, with their own open work and live releases for context. Use it for questions about what was decided, what is happening, or to find a record by name.';
    }

    public function status(): string
    {
        return 'Searching your workspace';
    }

    public function properties(): array
    {
        return ['query' => ['type' => 'string', 'description' => 'The question or key words.']];
    }

    public function required(): array
    {
        return ['query'];
    }

    public function run(array $input, User $user): ToolOutput
    {
        $records = $this->ask->retrieve((string) ($input['query'] ?? ''), $user);

        $text = $records->map(fn (array $r) => "[{$r['ref']}] {$r['kind']}: {$r['text']}")->implode("\n");
        $refs = $records->mapWithKeys(fn (array $r) => [$r['ref'] => collect($r)->only(['ref', 'kind', 'title', 'url'])->all()])->all();

        return new ToolOutput($text === '' ? 'No matching records.' : mb_strimwidth($text, 0, self::MAX_CHARS, '…'), $refs);
    }
}
