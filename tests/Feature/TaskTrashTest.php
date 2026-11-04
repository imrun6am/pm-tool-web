<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Deleting moves a task to the trash, where an admin can restore it for 30 days.
 */
class TaskTrashTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function card(string $title = 'Card', array $attrs = []): Task
    {
        return Task::create($attrs + ['title' => $title, 'status' => 'todo', 'position' => 0]);
    }

    private function subtaskOf(Task $parent, string $title = 'Part'): Task
    {
        return $this->card($title, ['parent_id' => $parent->id]);
    }

    public function test_deleting_keeps_everything_and_records_who(): void
    {
        $task = $this->card();
        $subtask = $this->subtaskOf($task);
        $task->comments()->create(['user_id' => $this->admin->id, 'body' => 'Hello']);
        $task->labels()->attach(Label::first());
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $this->admin->id, 'logged_on' => today(), 'minutes' => 30]);

        $this->actingAs($this->admin)->delete(route('tasks.destroy', $task))->assertRedirect();

        $this->assertSoftDeleted($task);
        $this->assertSoftDeleted($subtask);
        $this->assertSame($this->admin->id, Task::withTrashed()->find($task->id)->deleted_by);
        $this->assertSame(1, Comment::count());
        $this->assertSame(1, TaskTimeLog::count());
        $this->assertDatabaseCount('label_task', 1);
    }

    public function test_a_trashed_task_reads_as_gone(): void
    {
        $task = $this->card('Gone from view');
        $task->delete();

        $this->actingAs($this->admin)->get(route('tasks.show', $task))->assertNotFound();
        $this->withHeaders(['Turbo-Frame' => 'task-panel'])->get(route('tasks.panel', $task))->assertNotFound();
        $this->get(route('board.index'))->assertOk()->assertDontSee('Gone from view');
        $this->get(route('tasks.index'))->assertOk()->assertDontSee('Gone from view');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->admin);
        $this->getJson("/api/v1/tasks/{$task->id}")->assertNotFound();
        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/search/tasks?q=Gone')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_bulk_requests_refuse_a_trashed_id(): void
    {
        $task = $this->card();
        $task->delete();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/tasks/bulk', ['ids' => [$task->id], 'status' => 'done'])->assertStatus(422);
        $this->postJson('/api/v1/board/tasks/bulk-delete', ['ids' => [$task->id]])->assertStatus(422);
    }

    public function test_time_on_a_trashed_task_cannot_be_changed(): void
    {
        $task = $this->card('Card', ['assignee_id' => $this->admin->id]);
        $log = TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $this->admin->id, 'logged_on' => today(), 'minutes' => 30]);
        $task->delete();

        $this->actingAs($this->admin)->delete(route('time-logs.destroy', $log))->assertForbidden();
    }

    public function test_the_trash_page_lists_what_was_deleted(): void
    {
        $task = $this->card('Deleted card');
        $this->subtaskOf($task, 'Went along');
        $this->actingAs($this->admin);
        $task->delete();
        $this->card('Still here');

        $this->get(route('tasks.trash'))->assertOk()
            ->assertSee('id="task-trash"', false)
            ->assertSee('Deleted card')
            ->assertSee($this->admin->name)
            ->assertSee('30 days')
            ->assertDontSee('Went along')
            ->assertDontSee('Still here');
    }

    public function test_restoring_brings_back_the_task_with_all_it_had(): void
    {
        $task = $this->card('Back again');
        $subtask = $this->subtaskOf($task);
        $task->comments()->create(['user_id' => $this->admin->id, 'body' => 'Hello']);
        $log = TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $this->admin->id, 'logged_on' => today(), 'minutes' => 30]);
        $this->actingAs($this->admin);
        $task->delete();

        $this->from(route('tasks.trash'))->post(route('tasks.restore'), ['ids' => [$task->id]])
            ->assertRedirect(route('tasks.trash'))
            ->assertSessionHas('success', '1 task restored.');

        $fresh = Task::find($task->id);
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->deleted_by);
        $this->assertNotNull(Task::find($subtask->id));
        $this->assertFalse(Task::find($subtask->id)->trashed_with_parent);
        $this->assertCount(1, $fresh->comments);
        $this->assertSame($task->id, $log->fresh()->task_id);
        $this->get(route('board.index'))->assertSee('Back again');
    }

    public function test_a_subtask_deleted_on_its_own_stays_in_the_trash(): void
    {
        $task = $this->card();
        $earlier = $this->subtaskOf($task, 'Deleted earlier');
        $withParent = $this->subtaskOf($task, 'Went along');
        $this->actingAs($this->admin);
        $earlier->delete();
        $task->delete();

        $this->post(route('tasks.restore'), ['ids' => [$task->id]]);

        $this->assertNotNull(Task::find($withParent->id));
        $this->assertSoftDeleted($earlier);
        $this->get(route('tasks.trash'))->assertSee('Deleted earlier');
    }

    public function test_restoring_a_subtask_brings_back_its_parent(): void
    {
        $task = $this->card();
        $earlier = $this->subtaskOf($task);
        $this->actingAs($this->admin);
        $earlier->delete();
        $task->delete();

        $this->post(route('tasks.restore'), ['ids' => [$earlier->id]])->assertRedirect();

        $this->assertNotNull(Task::find($task->id));
        $this->assertNotNull(Task::find($earlier->id));
    }

    public function test_restoring_a_parent_and_its_child_together_is_harmless(): void
    {
        $task = $this->card();
        $subtask = $this->subtaskOf($task);
        $this->actingAs($this->admin);
        $task->delete();

        $this->post(route('tasks.restore'), ['ids' => [$subtask->id, $task->id]])->assertSessionHasNoErrors();

        $this->assertNotNull(Task::find($task->id));
        $this->assertNotNull(Task::find($subtask->id));
    }

    public function test_restoring_answers_json_for_the_boards_undo(): void
    {
        $task = $this->card();
        $this->actingAs($this->admin);
        $task->delete();

        $this->postJson(route('tasks.restore'), ['ids' => [$task->id]])->assertOk()
            ->assertJsonPath('restored_ids', [$task->id]);
    }

    public function test_only_trashed_tasks_can_be_restored(): void
    {
        $task = $this->card();

        $this->actingAs($this->admin)->postJson(route('tasks.restore'), ['ids' => [$task->id]])->assertStatus(422);
        $this->postJson(route('tasks.restore'), ['ids' => [999999]])->assertStatus(422);
        $this->postJson(route('tasks.restore'), [])->assertStatus(422);
    }

    public function test_restoring_is_in_the_activity_log_without_extra_noise(): void
    {
        $task = $this->card();
        $this->actingAs($this->admin);
        $task->delete();
        $this->post(route('tasks.restore'), ['ids' => [$task->id]]);

        $events = Activity::where('subject_id', $task->id)->where('subject_type', (new Task)->getMorphClass())->orderBy('id')->pluck('event')->all();

        $this->assertSame(['created', 'deleted', 'restored'], $events);
    }

    public function test_status_history_does_not_change_when_deleting_and_restoring(): void
    {
        $task = $this->card();
        $task->update(['status' => 'done']);
        $this->actingAs($this->admin);
        $task->delete();
        $this->post(route('tasks.restore'), ['ids' => [$task->id]]);

        $this->assertSame(['todo', 'done'], Task::find($task->id)->statusChanges()->pluck('to_status')->all());
    }

    public function test_restoring_follows_the_release_if_its_team_changed(): void
    {
        $team = Team::create(['name' => 'Old', 'color' => '#0891b2']);
        $task = $this->card('Card', ['team_id' => $team->id]);
        $this->actingAs($this->admin);
        $task->delete();

        $this->post(route('tasks.restore'), ['ids' => [$task->id]]);

        $this->assertSame($team->id, Task::find($task->id)->team_id);
    }

    public function test_deleting_for_good_removes_everything(): void
    {
        $task = $this->card();
        $subtask = $this->subtaskOf($task);
        $task->comments()->create(['user_id' => $this->admin->id, 'body' => 'Hello']);
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $this->admin->id, 'logged_on' => today(), 'minutes' => 30]);
        $this->actingAs($this->admin);
        $task->delete();

        $this->from(route('tasks.trash'))->delete(route('tasks.trash.destroy', $task->id))->assertRedirect(route('tasks.trash'));

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $subtask->id]);
        $this->assertSame(0, Comment::count());
        $this->assertSame(0, TaskTimeLog::count());
    }

    public function test_a_live_task_cannot_be_deleted_for_good(): void
    {
        $task = $this->card();

        $this->actingAs($this->admin)->delete(route('tasks.trash.destroy', $task->id))->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);
    }

    public function test_the_prune_removes_only_tasks_past_their_time(): void
    {
        $old = $this->card('Old');
        $recent = $this->card('Recent');
        $live = $this->card('Live');

        $this->travelTo(now()->subDays(31));
        $old->delete();
        $this->travelBack();
        $recent->delete();

        Artisan::call('model:prune', ['--model' => [Task::class]]);

        $this->assertDatabaseMissing('tasks', ['id' => $old->id]);
        $this->assertSoftDeleted($recent);
        $this->assertNotNull(Task::find($live->id));
    }

    public function test_the_prune_is_scheduled(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertTrue(collect($events)->contains(fn ($e) => str_contains($e->command ?? '', 'model:prune') && str_contains($e->command, 'Task')));
    }

    /** @return array<string, array{0: string}> */
    public static function nonAdminRoles(): array
    {
        return collect([User::ROLE_CTO, User::ROLE_TECH_LEAD, User::ROLE_TEAM_LEAD, User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER])
            ->mapWithKeys(fn ($role) => [$role => [$role]])->all();
    }

    #[DataProvider('nonAdminRoles')]
    public function test_only_admins_reach_the_trash(string $role): void
    {
        $task = $this->card();
        $task->delete();
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('tasks.trash'))->assertForbidden();
        $this->post(route('tasks.restore'), ['ids' => [$task->id]])->assertForbidden();
        $this->delete(route('tasks.trash.destroy', $task->id))->assertForbidden();
        $this->get(route('tasks.index'))->assertDontSee(route('tasks.trash'), false);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/tasks/trash')->assertForbidden();
        $this->postJson('/api/v1/tasks/restore', ['ids' => [$task->id]])->assertForbidden();
        $this->deleteJson("/api/v1/tasks/trash/{$task->id}")->assertForbidden();

        $this->assertSoftDeleted($task);
    }

    public function test_the_api_mirrors_the_web(): void
    {
        $task = $this->card('Back again');
        $gone = $this->card('For good');
        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertOk();
        $this->deleteJson("/api/v1/tasks/{$gone->id}")->assertOk();

        $this->getJson('/api/v1/tasks/trash')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);

        $this->postJson('/api/v1/tasks/restore', ['ids' => [$task->id]])->assertOk()
            ->assertJsonPath('data.restored_ids', [$task->id])
            ->assertJsonPath('message', '1 task restored.');

        $this->deleteJson("/api/v1/tasks/trash/{$gone->id}")->assertOk();

        $this->assertDatabaseMissing('tasks', ['id' => $gone->id]);
        $this->getJson("/api/v1/tasks/{$task->id}")->assertOk();
    }

    public function test_the_board_says_deleted_tasks_can_be_restored_and_links_the_trash(): void
    {
        $this->card();

        $this->actingAs($this->admin)->get(route('board.index'))->assertOk()
            ->assertSee('goes to the trash', false)
            ->assertSee(route('tasks.trash'), false)
            ->assertDontSee('can’t be undone', false);
    }
}
