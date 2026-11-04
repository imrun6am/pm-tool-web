<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskCompletions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every status a task passes through is kept in its own table, so delivery
 * reports don't depend on the activity log being kept.
 */
class TaskStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_04_100001_create_task_status_changes_table.php';

    public function test_every_status_change_is_recorded_with_who_and_from_what(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create(['title' => 'Card', 'status' => 'todo', 'position' => 0]);
        $task->update(['title' => 'Renamed', 'position' => 3]); // no status change: not history
        $task->update(['status' => 'in_review']);

        $changes = $task->statusChanges;

        $this->assertSame([null, 'todo'], $changes->pluck('from_status')->all());
        $this->assertSame(['todo', 'in_review'], $changes->pluck('to_status')->all());
        $this->assertSame([$user->id, $user->id], $changes->pluck('changed_by')->all());
    }

    public function test_a_task_created_without_a_status_starts_in_the_default_one(): void
    {
        $task = Task::create(['title' => 'Card', 'position' => 0]);

        $this->assertSame(['todo'], $task->statusChanges->pluck('to_status')->all());
    }

    public function test_a_board_move_and_a_status_update_are_both_recorded(): void
    {
        $user = User::factory()->create();
        $task = Task::create(['title' => 'Card', 'status' => 'todo', 'position' => 0]);

        $this->actingAs($user)->patchJson(route('board.move', $task), ['status' => 'in_progress'])->assertOk();
        $this->patch(route('tasks.status', $task), ['status' => 'done'])->assertRedirect();

        $this->assertSame(['todo', 'in_progress', 'done'], $task->statusChanges()->pluck('to_status')->all());
    }

    public function test_purging_a_task_removes_its_history(): void
    {
        $task = Task::create(['title' => 'Card', 'status' => 'todo', 'position' => 0]);

        $task->forceDelete();

        $this->assertDatabaseMissing('task_status_changes', ['task_id' => $task->id]);
    }

    public function test_completion_figures_survive_the_activity_log_being_pruned(): void
    {
        $this->travelTo('2026-10-01 09:00:00');
        $task = Task::create(['title' => 'Card', 'status' => 'todo', 'position' => 0]);
        $this->travelTo('2026-10-02 09:00:00');
        $task->update(['status' => 'in_progress']);
        $this->travelTo('2026-10-04 09:00:00');
        $task->update(['status' => 'recheck']);
        $this->travelTo('2026-10-05 09:00:00');
        $task->update(['status' => 'done']);

        DB::table('activities')->delete();

        $completion = app(TaskCompletions::class)
            ->since(fn () => Task::query(), now()->subDays(30))
            ->sole();

        $this->assertFalse($completion['estimated']);
        $this->assertTrue($completion['reworked']);
        $this->assertSame('2026-10-02 09:00:00', $completion['startedAt']->toDateTimeString());
        $this->assertSame('2026-10-05 09:00:00', $completion['completedAt']->toDateTimeString());
        $this->assertSame(3.0, TaskCompletions::avgCycleDays(collect([$completion])));
    }

    public function test_the_migration_brings_over_history_from_the_activity_log(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create(['title' => 'Kept', 'status' => 'todo', 'position' => 0]);
        $task->update(['status' => 'in_progress']);
        $task->update(['title' => 'Renamed']); // no status change: not history
        $task->update(['status' => 'done']);

        $gone = Task::create(['title' => 'Gone', 'status' => 'todo', 'position' => 0]);
        $gone->forceDelete();

        Schema::dropIfExists('task_status_changes');
        (require base_path(self::MIGRATION))->up();

        $rows = DB::table('task_status_changes')->orderBy('id')->get();

        $this->assertSame([$task->id, $task->id, $task->id], $rows->pluck('task_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([null, 'todo', 'in_progress'], $rows->pluck('from_status')->all());
        $this->assertSame(['todo', 'in_progress', 'done'], $rows->pluck('to_status')->all());
        $this->assertSame($user->id, (int) $rows->first()->changed_by);
    }

    public function test_the_migration_keeps_a_change_by_a_user_who_no_longer_exists(): void
    {
        $task = Task::create(['title' => 'Kept', 'status' => 'todo', 'position' => 0]);
        DB::table('activities')->where('subject_id', $task->id)->update(['causer_id' => null]);

        Schema::dropIfExists('task_status_changes');
        (require base_path(self::MIGRATION))->up();

        $this->assertDatabaseHas('task_status_changes', ['task_id' => $task->id, 'to_status' => 'todo', 'changed_by' => null]);
    }

    public function test_the_migration_copes_with_an_empty_activity_log(): void
    {
        DB::table('activities')->delete();

        Schema::dropIfExists('task_status_changes');
        (require base_path(self::MIGRATION))->up();

        $this->assertSame(0, DB::table('task_status_changes')->count());
    }
}
