<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Archived tasks are filed away: they take no part in any task count, total,
 * or progress figure — neither as open work nor as done work.
 */
class ArchivedTasksNotCountedTest extends TestCase
{
    use RefreshDatabase;

    private function release(): Release
    {
        return Release::create([
            'project_id' => Project::create(['name' => 'Shop', 'color' => '#0891b2'])->id,
            'team_id' => Team::create(['name' => 'Alpha', 'color' => '#4f46e5'])->id,
            'name' => 'Checkout v2',
            'year' => 2026, 'quarter' => 3,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        ]);
    }

    public function test_project_progress_and_task_mix_leave_archived_tasks_out(): void
    {
        $release = $this->release();
        foreach (['todo', 'done', 'archive', 'archive'] as $i => $status) {
            Task::create(['release_id' => $release->id, 'title' => "T{$i}", 'status' => $status]);
        }

        $service = app(ProjectService::class);
        $releases = $service->releasesWithProgress($release->project);

        $this->assertSame(2, $releases->first()->tasks_count);
        $this->assertSame(1, $releases->first()->done_tasks_count);

        $stats = $service->analytics($release->project, $releases);
        $this->assertSame(2, $stats['taskTotal']);
        $this->assertArrayNotHasKey('archive', $stats['statusCounts']);
    }

    public function test_subtask_progress_leaves_archived_subtasks_out(): void
    {
        $release = $this->release();
        $parent = Task::create(['release_id' => $release->id, 'title' => 'Parent', 'status' => 'todo']);
        foreach (['todo', 'done', 'archive'] as $i => $status) {
            Task::create(['release_id' => $release->id, 'parent_id' => $parent->id, 'title' => "S{$i}", 'status' => $status]);
        }

        $this->assertSame(['done' => 1, 'total' => 2], $parent->fresh()->subtaskProgress());
    }

    public function test_archived_tasks_are_still_never_open(): void
    {
        $this->assertContains('archive', Task::DONE_STATUSES);
        $this->assertNotContains('archive', Task::countedStatuses());
    }
}
