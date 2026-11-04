<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TaskBlocked;
use App\Notifications\TaskUnblocked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A task is blocked when someone flags it with a reason, or while it waits for
 * an unfinished task.
 */
class TaskBlockingTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $dev;

    private User $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2', 'team_lead_id' => $this->lead->id]);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($this->dev);
    }

    private function card(string $title = 'Card', array $attrs = []): Task
    {
        return Task::create($attrs + ['title' => $title, 'status' => 'todo', 'position' => 0, 'team_id' => $this->team->id]);
    }

    public function test_flagging_records_the_reason_and_shows_on_the_page_and_board(): void
    {
        $task = $this->card();

        $this->actingAs($this->dev)->from(route('tasks.show', $task))
            ->post(route('tasks.block', $task), ['reason' => 'Waiting for sandbox keys'])
            ->assertRedirect(route('tasks.show', $task));

        $task->refresh();
        $this->assertNotNull($task->blocked_at);
        $this->assertSame('Waiting for sandbox keys', $task->blocked_reason);
        $this->assertTrue($task->isBlocked());

        $this->get(route('tasks.show', $task))->assertOk()
            ->assertSee('id="task-blocking"', false)
            ->assertSee('Waiting for sandbox keys');

        $this->get(route('board.index', ['assignee_id' => 'anyone']))->assertOk()->assertSee('Waiting for sandbox keys');
    }

    public function test_a_reason_is_required(): void
    {
        $task = $this->card();

        $this->actingAs($this->dev)->post(route('tasks.block', $task), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->assertNull($task->fresh()->blocked_at);
    }

    public function test_changing_the_reason_keeps_when_it_was_blocked(): void
    {
        $task = $this->card();
        $this->travelTo('2026-11-01 09:00:00');
        $this->actingAs($this->dev)->post(route('tasks.block', $task), ['reason' => 'First']);
        $this->travelTo('2026-11-02 09:00:00');
        $this->post(route('tasks.block', $task), ['reason' => 'Second']);

        $task->refresh();
        $this->assertSame('Second', $task->blocked_reason);
        $this->assertSame('2026-11-01 09:00:00', $task->blocked_at->toDateTimeString());
    }

    public function test_unblocking_clears_the_flag(): void
    {
        $task = $this->card('Card', ['blocked_at' => now(), 'blocked_reason' => 'Stuck']);

        $this->actingAs($this->dev)->delete(route('tasks.unblock', $task))->assertRedirect();

        $task->refresh();
        $this->assertNull($task->blocked_at);
        $this->assertNull($task->blocked_reason);
        $this->assertFalse($task->isBlocked());
    }

    public function test_finishing_a_task_clears_its_flag(): void
    {
        $task = $this->card('Card', ['blocked_at' => now(), 'blocked_reason' => 'Stuck']);

        $task->update(['status' => 'done']);

        $this->assertNull($task->fresh()->blocked_at);
        $this->assertFalse($task->fresh()->isBlocked());
    }

    public function test_waiting_for_an_unfinished_task_blocks_until_it_finishes(): void
    {
        $task = $this->card('Waiting');
        $blocker = $this->card('Blocker');

        $this->actingAs($this->dev)->post(route('tasks.dependencies.store', $task), ['reference' => $blocker->reference()])->assertRedirect();

        $this->assertTrue($task->fresh()->isBlocked());
        $this->get(route('tasks.show', $task))->assertSee('Blocker');
        $this->get(route('tasks.show', $blocker))->assertSee('Holding up')->assertSee('Waiting');

        $blocker->update(['status' => 'done']);

        $this->assertFalse($task->fresh()->isBlocked());
    }

    public function test_removing_a_dependency_unblocks(): void
    {
        $task = $this->card('Waiting');
        $blocker = $this->card('Blocker');
        $task->blockers()->attach($blocker);

        $this->actingAs($this->dev)->delete(route('tasks.dependencies.destroy', [$task, $blocker]))->assertRedirect();

        $this->assertFalse($task->fresh()->isBlocked());
        $this->assertDatabaseHas('tasks', ['id' => $blocker->id]);
    }

    public function test_adding_the_same_dependency_twice_is_harmless(): void
    {
        $task = $this->card('Waiting');
        $blocker = $this->card('Blocker');

        $this->actingAs($this->dev);
        $this->post(route('tasks.dependencies.store', $task), ['reference' => (string) $blocker->id])->assertSessionHasNoErrors();
        $this->post(route('tasks.dependencies.store', $task), ['reference' => $blocker->reference()])->assertSessionHasNoErrors();

        $this->assertCount(1, $task->blockers);
    }

    public function test_a_task_cannot_wait_for_itself(): void
    {
        $task = $this->card();

        $this->actingAs($this->dev)->post(route('tasks.dependencies.store', $task), ['reference' => $task->reference()])
            ->assertSessionHasErrors('reference');
    }

    public function test_a_loop_is_refused_directly_and_through_another_task(): void
    {
        [$a, $b, $c] = [$this->card('A'), $this->card('B'), $this->card('C')];
        $a->blockers()->attach($b);
        $b->blockers()->attach($c);

        $this->actingAs($this->dev);
        $this->post(route('tasks.dependencies.store', $b), ['reference' => $a->reference()])->assertSessionHasErrors('reference');
        $this->post(route('tasks.dependencies.store', $c), ['reference' => $a->reference()])->assertSessionHasErrors('reference');

        $this->assertCount(0, $c->fresh()->blockers);
    }

    public function test_a_task_out_of_reach_or_missing_reads_the_same(): void
    {
        $task = $this->card();
        $other = Team::create(['name' => 'Other', 'color' => '#e11d48']);
        $hidden = Task::create(['title' => 'Hidden', 'status' => 'todo', 'position' => 0, 'team_id' => $other->id]);

        $this->actingAs($this->dev);
        $hiddenError = $this->post(route('tasks.dependencies.store', $task), ['reference' => $hidden->reference()])
            ->assertSessionHasErrors('reference')->getSession()->get('errors')->first('reference');
        $missingError = $this->post(route('tasks.dependencies.store', $task), ['reference' => 'PM-999999'])
            ->assertSessionHasErrors('reference')->getSession()->get('errors')->first('reference');

        $this->assertSame($missingError, $hiddenError);
    }

    public function test_someone_outside_the_team_cannot_change_a_tasks_blocking(): void
    {
        $task = $this->card();
        $blocker = $this->card('Blocker');
        $task->blockers()->attach($blocker);
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($outsider);
        $this->post(route('tasks.block', $task), ['reason' => 'X'])->assertForbidden();
        $this->delete(route('tasks.unblock', $task))->assertForbidden();
        $this->post(route('tasks.dependencies.store', $task), ['reference' => $blocker->reference()])->assertForbidden();
        $this->delete(route('tasks.dependencies.destroy', [$task, $blocker]))->assertForbidden();

        $this->assertCount(1, $task->fresh()->blockers);
    }

    public function test_a_teammate_never_sees_another_teams_blocker_but_the_task_still_reads_blocked(): void
    {
        $task = $this->card('Ours', ['assignee_id' => $this->dev->id]);
        $other = Team::create(['name' => 'Other', 'color' => '#e11d48']);
        $hidden = Task::create(['title' => 'Secret other team work', 'status' => 'todo', 'position' => 0, 'team_id' => $other->id]);
        $task->blockers()->attach($hidden);

        $this->actingAs($this->dev)->get(route('tasks.show', $task))->assertOk()
            ->assertSee('Blocked')
            ->assertDontSee('Secret other team work');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->dev);
        $this->getJson("/api/v1/tasks/{$task->id}")->assertOk()
            ->assertJsonPath('data.is_blocked', true)
            ->assertJsonCount(0, 'data.blockers');
    }

    public function test_the_api_mirrors_the_web(): void
    {
        $task = $this->card();
        $blocker = $this->card('Blocker');
        Sanctum::actingAs($this->dev);

        $this->postJson("/api/v1/tasks/{$task->id}/block", ['reason' => 'Stuck'])->assertOk()
            ->assertJsonPath('data.is_blocked', true)
            ->assertJsonPath('data.blocked_reason', 'Stuck');
        $this->deleteJson("/api/v1/tasks/{$task->id}/block")->assertOk()
            ->assertJsonPath('data.is_blocked', false)
            ->assertJsonPath('data.blocked_reason', null);

        $this->postJson("/api/v1/tasks/{$task->id}/dependencies", ['reference' => $blocker->reference()])->assertOk()
            ->assertJsonPath('data.is_blocked', true)
            ->assertJsonPath('data.blockers.0.key', $blocker->reference());
        $this->getJson("/api/v1/tasks/{$blocker->id}")->assertOk()
            ->assertJsonPath('data.blocking.0.id', $task->id);
        $this->deleteJson("/api/v1/tasks/{$task->id}/dependencies/{$blocker->id}")->assertOk()
            ->assertJsonPath('data.is_blocked', false);

        $this->postJson("/api/v1/tasks/{$task->id}/block", [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/tasks/{$task->id}/dependencies", ['reference' => $task->reference()])->assertStatus(422)->assertJsonValidationErrors('reference');
    }

    public function test_the_blocked_filter_selects_flagged_and_waiting_tasks_only(): void
    {
        $flagged = $this->card('Flagged', ['blocked_at' => now(), 'blocked_reason' => 'Stuck']);
        $waiting = $this->card('Waiting');
        $open = $this->card('Open blocker');
        $waiting->blockers()->attach($open);
        $released = $this->card('Released');
        $released->blockers()->attach($this->card('Finished', ['status' => 'done']));

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $board = collect($this->actingAs($admin)->get(route('board.index', ['blocked' => 1]))->viewData('columns'))->flatten()->pluck('id')->sort()->values()->all();
        $this->assertSame([$flagged->id, $waiting->id], $board);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin);
        $list = collect($this->getJson('/api/v1/tasks?blocked=1')->assertOk()->json('data'));
        $this->assertSame([$flagged->id, $waiting->id], $list->pluck('id')->sort()->values()->all());
        $this->assertTrue($list->every(fn ($t) => $t['is_blocked']));
    }

    public function test_the_board_does_not_query_per_card_for_blocked_status(): void
    {
        foreach (range(1, 5) as $i) {
            $this->card("Card {$i}");
        }
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $queries = 0;
        DB::listen(function ($q) use (&$queries) {
            if (str_contains($q->sql, 'task_dependencies')) {
                $queries++;
            }
        });

        $this->actingAs($admin)->get(route('board.index'))->assertOk();

        $this->assertSame(1, $queries);
    }

    public function test_flagging_tells_the_team_lead_and_assignee_but_not_the_person_flagging(): void
    {
        Notification::fake();
        $assignee = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($assignee);
        $task = $this->card('Card', ['assignee_id' => $assignee->id]);

        $this->actingAs($this->dev)->post(route('tasks.block', $task), ['reason' => 'Stuck']);

        Notification::assertSentTo($this->lead, TaskBlocked::class);
        Notification::assertSentTo($assignee, TaskBlocked::class);
        Notification::assertNotSentTo($this->dev, TaskBlocked::class);
    }

    public function test_changing_only_the_reason_does_not_notify_again(): void
    {
        $task = $this->card();
        $this->actingAs($this->dev)->post(route('tasks.block', $task), ['reason' => 'First']);

        Notification::fake();
        $this->post(route('tasks.block', $task), ['reason' => 'Second']);

        Notification::assertNothingSent();
    }

    public function test_the_blocked_notification_reads_well_in_the_inbox(): void
    {
        $task = $this->card('Payment API');

        $this->actingAs($this->dev)->post(route('tasks.block', $task), ['reason' => 'Waiting for keys']);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->lead)->get(route('notifications.index'))->assertOk()
            ->assertSee('marked “Payment API” as blocked', false)
            ->assertSee('Waiting for keys');
    }

    public function test_the_assignee_hears_when_the_last_blocker_finishes(): void
    {
        $assignee = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($assignee);
        $task = $this->card('Waiting', ['assignee_id' => $assignee->id]);
        [$first, $second] = [$this->card('First'), $this->card('Second')];
        $task->blockers()->attach([$first->id, $second->id]);

        Notification::fake();
        $this->actingAs($this->dev);

        $first->update(['status' => 'done']);
        Notification::assertNotSentTo($assignee, TaskUnblocked::class);

        $second->update(['status' => 'done']);
        Notification::assertSentTo($assignee, TaskUnblocked::class);
    }

    public function test_a_task_still_flagged_gets_no_ready_notification(): void
    {
        $assignee = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($assignee);
        $task = $this->card('Waiting', ['assignee_id' => $assignee->id, 'blocked_at' => now(), 'blocked_reason' => 'Other reason']);
        $blocker = $this->card('Blocker');
        $task->blockers()->attach($blocker);

        Notification::fake();
        $this->actingAs($this->dev);
        $blocker->update(['status' => 'done']);

        Notification::assertNotSentTo($assignee, TaskUnblocked::class);
    }

    public function test_trashing_a_blocker_unblocks_and_purging_removes_the_link(): void
    {
        $task = $this->card('Waiting');
        $blocker = $this->card('Blocker');
        $task->blockers()->attach($blocker);

        $blocker->delete();
        $this->assertFalse($task->fresh()->isBlocked());

        $blocker->forceDelete();
        $this->assertDatabaseMissing('task_dependencies', ['task_id' => $task->id]);
    }
}
