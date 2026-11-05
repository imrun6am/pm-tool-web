<?php

namespace App\Services\Board;

use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\User;
use App\Services\Kpi\MissingApiKeyException;
use App\Services\NotificationService;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tasksheet "Add to board": turns a row's morning plan into To Do cards and
 * subtasks on the board, assigned to the row's member, in the chosen release or
 * in none. Work the member still has open there (matched by meaning through the
 * AI and by exact title here) or repeated within the batch is skipped, so editing
 * the plan and pressing again only adds new items. New sub-items of a main task
 * already open on the board are added to it, and a main task that isn't there is
 * created with them. Done and archived main tasks don't count: the same work can
 * come round again.
 */
class PlanToBoardService
{
    /** How many of the member's existing main tasks are shown to the AI for dedupe. */
    private const EXISTING_LIMIT = 200;

    /** How many other open main tasks are shown to the AI as possible parents. */
    private const TEAM_TASKS_LIMIT = 100;

    public function __construct(
        private readonly PlanTaskExtractor $extractor,
        private readonly NotificationService $notifications,
        private readonly TaskService $tasks,
    ) {}

    /**
     * addPlanTasks() with its outcome worded for the user, so the sheet and the
     * API say the same thing. An AI failure is an outcome here, not an exception.
     *
     * @return array{ok: bool, message: string, created: list<Task>, subtasks: list<Task>, skipped: int}
     */
    public function attempt(TasksheetEntry $entry, ?Release $release, User $actor): array
    {
        $nothing = ['created' => [], 'subtasks' => [], 'skipped' => 0];

        try {
            $result = $this->addPlanTasks($entry, $release, $actor);
        } catch (MissingApiKeyException) {
            return $nothing + ['ok' => false, 'message' => 'Tasksheet saved, but no tasks were added: AI isn’t configured (ANTHROPIC_API_KEY).'];
        } catch (Throwable $e) {
            report($e);

            return $nothing + ['ok' => false, 'message' => 'Tasksheet saved, but no tasks were added: the AI couldn’t split the plan right now. Please try again.'];
        }

        return $result + ['ok' => true, 'message' => self::summary($result, $entry->member->name)];
    }

    /**
     * @return array{created: list<Task>, subtasks: list<Task>, skipped: int}
     */
    public function addPlanTasks(TasksheetEntry $entry, ?Release $release, User $actor): array
    {
        $own = $this->ownWork($entry, $release);
        $team = $this->teamWork($entry, $release, $actor, $own);

        // The AI leaves out plan items an existing task already covers, even when
        // worded differently; the exact-title checks in place() are a safety net.
        $result = $this->extractor->extract(
            self::plainText((string) $entry->plan),
            filled($entry->tickets) ? self::plainText($entry->tickets) : null,
            $own->map(fn (Task $task) => [
                'title' => $task->title,
                'subtasks' => $task->subtasks->pluck('title')->all(),
            ])->all(),
            $team->pluck('title')->all(),
        );

        return $this->notifications->batch(fn () => DB::transaction(
            fn () => $this->place($result['tasks'], $result['covered'], $own, $team, $entry, $release, $actor)
        ));
    }

    /**
     * The member's open main tasks in this release (or unlinked), counting one
     * they hold an open subtask of, with all of each task's subtasks.
     *
     * @return Collection<int, Task>
     */
    private function ownWork(TasksheetEntry $entry, ?Release $release): Collection
    {
        return Task::query()
            ->where('release_id', $release?->id)
            ->whereNull('parent_id')
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->where(fn ($q) => $q
                ->where('assignee_id', $entry->user_id)
                ->orWhereHas('subtasks', fn ($sub) => $sub
                    ->where('assignee_id', $entry->user_id)
                    ->whereNotIn('status', Task::DONE_STATUSES)))
            ->with('subtasks')
            ->latest('id')
            ->limit(self::EXISTING_LIMIT)
            ->get();
    }

    /**
     * Other open main tasks new subtasks could go under: the release's, or with
     * no release the row team's unlinked ones, and only those the actor may see.
     *
     * @param  Collection<int, Task>  $own
     * @return Collection<int, Task>
     */
    private function teamWork(TasksheetEntry $entry, ?Release $release, User $actor, Collection $own): Collection
    {
        return Task::query()
            ->visibleTo($actor)
            ->where('release_id', $release?->id)
            ->when(! $release, fn ($q) => $q->where('team_id', $entry->team_id))
            ->whereNull('parent_id')
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->whereNotIn('id', $own->pluck('id'))
            ->latest('id')
            ->limit(self::TEAM_TASKS_LIMIT)
            ->get();
    }

    /**
     * Put what the AI returned on the board. A main task reuses a matching open
     * task (a teammate's only when it has subtasks to take) or is created.
     *
     * @param  list<array{title: string, description: ?string, subtasks?: list<array{title: string, description: ?string}>}>  $items
     * @param  Collection<int, Task>  $own
     * @param  Collection<int, Task>  $team
     * @return array{created: list<Task>, subtasks: list<Task>, skipped: int}
     */
    private function place(array $items, int $covered, Collection $own, Collection $team, TasksheetEntry $entry, ?Release $release, User $actor): array
    {
        $mains = $own->keyBy(fn (Task $task) => self::key($task->title))->all();
        $teamMains = $team->keyBy(fn (Task $task) => self::key($task->title))->all();

        // An existing subtask's title → its parent, since nesting is one level.
        $parentOfSubtask = [];
        foreach ($own as $task) {
            foreach ($task->subtasks as $subtask) {
                $parentOfSubtask[self::key($subtask->title)] ??= $task;
            }
        }

        $position = TaskService::nextPosition($release?->id);
        $children = [];
        $created = [];
        $subtasks = [];
        $skipped = $covered;

        foreach ($items as $item) {
            $key = self::key($item['title']);
            $steps = $item['subtasks'] ?? [];
            $parent = $mains[$key] ?? $parentOfSubtask[$key] ?? ($steps !== [] ? $teamMains[$key] ?? null : null);

            if ($parent) {
                $skipped++;
            } else {
                $parent = $mains[$key] = $created[] = Task::create([
                    'release_id' => $release?->id,
                    'team_id' => $entry->team_id,
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'status' => 'todo',
                    'parent_id' => null,
                    'assignee_id' => $entry->user_id,
                    'created_by' => $actor->id,
                    'position' => $position++,
                ]);
            }

            // Any status counts here: the parent is still open, so a finished
            // step is the same step, not work coming round again.
            $children[$parent->id] ??= $parent->subtasks()->pluck('title')
                ->mapWithKeys(fn (string $title) => [self::key($title) => true])
                ->all();

            foreach ($steps as $step) {
                $stepKey = self::key($step['title']);
                if ($stepKey === self::key($parent->title) || isset($children[$parent->id][$stepKey]) || isset($mains[$stepKey])) {
                    $skipped++;

                    continue;
                }

                $children[$parent->id][$stepKey] = true;
                $parentOfSubtask[$stepKey] ??= $parent;
                $subtasks[] = $this->tasks->createSubtask($parent, [
                    'title' => $step['title'],
                    'description' => $step['description'],
                    'status' => 'todo',
                    'assignee_id' => $entry->user_id,
                ], $actor);
            }
        }

        return ['created' => $created, 'subtasks' => $subtasks, 'skipped' => $skipped];
    }

    /**
     * @param  array{created: list<Task>, subtasks: list<Task>, skipped: int}  $result
     */
    private static function summary(array $result, string $name): string
    {
        $added = collect(['task' => count($result['created']), 'subtask' => count($result['subtasks'])])
            ->filter()
            ->map(fn (int $count, string $noun) => $count.' '.Str::plural($noun, $count))
            ->implode(' and ');

        if ($added === '') {
            return $result['skipped'] > 0
                ? "Tasksheet saved. Those tasks are already on the board for {$name}."
                : 'Tasksheet saved. The AI found no actionable tasks in the plan.';
        }

        return "Tasksheet saved. Added {$added} to the board for {$name}."
            .($result['skipped'] > 0 ? " Skipped {$result['skipped']} already on the board." : '');
    }

    /** Trix HTML → plain text, keeping one line per block / list item and indenting nested items. */
    public static function plainText(string $html): string
    {
        // A nested list closes just before its parent item does: one line end is enough.
        $text = preg_replace('#</(ul|ol)>\s*</li>#i', '</$1>', $html);

        $depth = 0;
        $text = preg_replace_callback('#<(/?)(?:ul|ol)\b[^>]*>|<li\b[^>]*>#i', function (array $tag) use (&$depth) {
            if (stripos($tag[0], '<li') === 0) {
                return str_repeat('  ', max(0, $depth - 1)).'- ';
            }
            if ($tag[1] === '/') {
                $depth = max(0, $depth - 1);

                return '';
            }

            return ++$depth > 1 ? "\n" : '';
        }, $text);

        $text = preg_replace('#<br\s*/?>|</(div|p|li|h[1-6]|blockquote|pre)>#i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/[ \t]+\n|\n{3,}/", "\n", $text));
    }

    private static function key(string $title): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $title)));
    }
}
