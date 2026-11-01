<?php

namespace App\Services\Board;

use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Support\Facades\DB;

/**
 * Tasksheet "Add to board": turns a row's morning plan into To Do cards on
 * the board, assigned to the row's member, in the chosen release or in none.
 * Work the member already has there (any status, matched by meaning through
 * Claude and by exact title here) or repeated within the batch is skipped, so
 * editing the plan and pressing again only ever adds the new items.
 */
class PlanToBoardService
{
    /** How many of the member's existing titles are shown to Claude for dedupe. */
    private const EXISTING_LIMIT = 200;

    public function __construct(private readonly PlanTaskExtractor $extractor) {}

    /**
     * @return array{created: list<Task>, skipped: int}
     */
    public function addPlanTasks(TasksheetEntry $entry, ?Release $release, User $actor): array
    {
        // Everything the member already has in this release (or unlinked) — done
        // and archived included — so pressing again only adds new work.
        $existing = Task::query()
            ->where('release_id', $release?->id)
            ->whereNull('parent_id')
            ->where('assignee_id', $entry->user_id)
            ->latest('id')
            ->limit(self::EXISTING_LIMIT)
            ->pluck('title');

        // Claude leaves out plan items an existing task already covers, even when
        // worded differently; the exact-title check below is a safety net.
        $result = $this->extractor->extract(
            self::plainText((string) $entry->plan),
            filled($entry->tickets) ? self::plainText($entry->tickets) : null,
            $existing->all(),
        );
        $tasks = $result['tasks'];

        $seen = $existing->mapWithKeys(fn (string $title) => [self::key($title) => true])->all();

        $fresh = [];
        foreach ($tasks as $task) {
            $key = self::key($task['title']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $fresh[] = $task;
        }

        $created = DB::transaction(function () use ($fresh, $release, $entry, $actor) {
            $position = TaskService::nextPosition($release?->id);
            $created = [];
            foreach ($fresh as $task) {
                $created[] = Task::create([
                    'release_id' => $release?->id,
                    'team_id' => $entry->team_id,
                    'title' => $task['title'],
                    'description' => $task['description'],
                    'status' => 'todo',
                    'parent_id' => null,
                    'assignee_id' => $entry->user_id,
                    'created_by' => $actor->id,
                    'position' => $position++,
                ]);
            }

            return $created;
        });

        return ['created' => $created, 'skipped' => $result['covered'] + count($tasks) - count($fresh)];
    }

    /** Trix HTML → plain text, keeping one line per block / list item. */
    public static function plainText(string $html): string
    {
        $text = preg_replace('#<br\s*/?>|</(div|p|li|h[1-6]|blockquote|pre)>#i', "\n", $html);
        $text = preg_replace('#<li[^>]*>#i', '- ', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/[ \t]+\n|\n{3,}/", "\n", $text));
    }

    private static function key(string $title): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $title)));
    }
}
