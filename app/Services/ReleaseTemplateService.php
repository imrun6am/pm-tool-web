<?php

namespace App\Services;

use App\Models\Release;
use App\Models\ReleaseTemplate;
use App\Models\ReleaseTemplateTask;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Standard task sets, and starting a release from one. */
class ReleaseTemplateService
{
    public function __construct(private readonly TaskService $tasks) {}

    /** @return Collection<int, ReleaseTemplate> */
    public function all(): Collection
    {
        return ReleaseTemplate::with('tasks')->orderBy('name')->get();
    }

    public function create(string $name, User $author): ReleaseTemplate
    {
        return ReleaseTemplate::create(['name' => $name, 'created_by' => $author->id]);
    }

    public function delete(ReleaseTemplate $template): void
    {
        $template->delete();
    }

    /** @param  array<string, mixed>  $data */
    public function addTask(ReleaseTemplate $template, array $data): ReleaseTemplateTask
    {
        return $template->tasks()->create($data + ['position' => $template->tasks()->count()]);
    }

    public function removeTask(ReleaseTemplateTask $task): void
    {
        $task->delete();
    }

    /**
     * Add the template's tasks to a release, due when their phase ends. Titles
     * the release already has are skipped, so applying twice is harmless.
     *
     * @return int how many tasks were created
     */
    public function apply(ReleaseTemplate $template, Release $release, User $author): int
    {
        return DB::transaction(function () use ($template, $release, $author) {
            $existing = $release->tasks()->whereNull('parent_id')->pluck('title')->all();
            $phaseEnds = $release->phases()->pluck('end_date', 'phase');
            $created = 0;

            foreach ($template->tasks as $task) {
                if (in_array($task->title, $existing, true)) {
                    continue;
                }

                $end = $task->phase ? ($phaseEnds[$task->phase] ?? null) : null;

                $this->tasks->createForRelease($release, [
                    'title' => $task->title,
                    'phase' => $task->phase,
                    'priority' => $task->priority,
                    'estimate_minutes' => $task->estimate_minutes,
                    'due_date' => $end ? $end->toDateString() : null,
                ], $author);
                $created++;
            }

            return $created;
        });
    }
}
