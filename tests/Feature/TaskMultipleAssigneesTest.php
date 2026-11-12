<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\TaskAssignees;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * openspec/changes/task-multiple-assignees: several people on one task, on the
 * web and through the API, with filters, visibility, notices and the rules.
 */
class TaskMultipleAssigneesTest extends TestCase
{
    use RefreshDatabase;

    private Team $core;

    private Team $growth;

    private Release $release;

    private User $lead;

    private User $ana;

    private User $ben;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->core = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->growth = Team::create(['name' => 'Growth', 'color' => '#e11d48']);
        $project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        $this->release = Release::create([
            'project_id' => $project->id, 'team_id' => $this->core->id, 'name' => 'Core R1',
            'year' => 2026, 'quarter' => 4, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
        ]);

        $this->lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD, 'name' => 'Lee Lead']);
        $this->ana = $this->member($this->core, 'Ana Dev');
        $this->ben = $this->member($this->core, 'Ben QA', User::ROLE_QA);
        $this->outsider = $this->member($this->growth, 'Olly Outside');
    }

    private function member(Team $team, string $name, string $role = User::ROLE_DEVELOPER): User
    {
        $user = User::factory()->create(['role' => $role, 'name' => $name]);
        $team->members()->attach($user);

        return $user;
    }

    private function task(array $attrs = []): Task
    {
        return Task::create($attrs + [
            'team_id' => $this->core->id, 'title' => 'Shared work', 'status' => 'todo', 'position' => 0,
            'created_by' => $this->lead->id,
        ]);
    }

    /** @return list<string> */
    private function kinds(User $user): array
    {
        return $user->notifications()->orderBy('created_at')->get()->pluck('data.kind')->all();
    }

    private function clearInboxes(): void
    {
        DB::table('notifications')->delete();
    }

    /** @return list<int> */
    private function ids(Task $task): array
    {
        return $task->fresh()->assigneeIds();
    }

    // ── Data and the lead ──────────────────────────────────────────────────

    public function test_the_migration_copies_every_existing_assignee(): void
    {
        $assigned = $this->task(['assignee_id' => $this->ana->id]);
        $trashed = $this->task(['assignee_id' => $this->ben->id, 'title' => 'Old']);
        $trashed->delete();
        $unassigned = $this->task(['title' => 'Nobody']);
        DB::table('task_user')->delete(); // as the data stood before the migration

        $migration = require database_path('migrations/2026_11_12_100001_create_task_user_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame([$this->ana->id], $this->ids($assigned));
        $this->assertSame($this->ana->id, $assigned->fresh()->assignee_id);
        $this->assertSame([$this->ben->id], DB::table('task_user')->where('task_id', $trashed->id)->pluck('user_id')->all());
        $this->assertSame([], $this->ids($unassigned));
    }

    public function test_the_first_person_chosen_leads_and_the_next_one_takes_over(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task();

        $this->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids' => [$this->ana->id, $this->ben->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame([$this->ana->id, $this->ben->id], $this->ids($task));
        $this->assertSame($this->ana->id, $task->fresh()->assignee_id);

        $this->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids' => [$this->ben->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->ben->id, $task->fresh()->assignee_id);

        $this->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids_sent' => '1', 'assignee_ids' => ['']])
            ->assertSessionHasNoErrors();
        $this->assertSame([], $this->ids($task));
        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_a_subtask_takes_several_assignees(): void
    {
        $parent = $this->task(['release_id' => $this->release->id]);

        $this->actingAs($this->lead)
            ->post(route('tasks.subtasks.store', $parent), ['title' => 'Pair on it', 'assignee_ids' => [$this->ben->id, $this->ana->id]])
            ->assertSessionHasNoErrors();

        $subtask = $parent->subtasks()->first();
        $this->assertSame([$this->ben->id, $this->ana->id], $this->ids($subtask));
        $this->assertSame($this->ben->id, $subtask->assignee_id);
    }

    public function test_a_task_takes_at_most_ten_assignees(): void
    {
        $people = User::factory()->count(11)->create(['role' => User::ROLE_DEVELOPER]);
        $task = $this->task(['team_id' => null]);

        $this->actingAs($this->lead)
            ->put(route('tasks.update', $task), ['title' => 'Big', 'assignee_ids' => $people->pluck('id')->all()])
            ->assertSessionHasErrors('assignee_ids');
    }

    // ── The API and older clients ──────────────────────────────────────────

    public function test_the_api_reads_and_writes_the_list(): void
    {
        Sanctum::actingAs($this->lead);

        $created = $this->postJson('/api/v1/tasks', ['title' => 'Via API', 'team_id' => $this->core->id, 'assignee_ids' => [$this->ben->id, $this->ana->id]])
            ->assertCreated();
        $this->assertSame([$this->ben->id, $this->ana->id], $created->json('data.assignee_ids'));
        $this->assertSame($this->ben->id, $created->json('data.assignee_id'));
        $this->assertSame([$this->ben->id, $this->ana->id], collect($created->json('data.assignees'))->pluck('id')->all());

        // Both sent: the list wins and assignee_id picks the lead.
        $id = $created->json('data.id');
        $this->putJson("/api/v1/tasks/{$id}", ['title' => 'Via API', 'assignee_ids' => [$this->ben->id, $this->ana->id], 'assignee_id' => $this->ana->id])
            ->assertOk()->assertJsonPath('data.assignee_id', $this->ana->id);
    }

    public function test_an_older_client_resaving_the_lead_keeps_everyone(): void
    {
        $task = $this->task(['assignee_id' => $this->ana->id]);
        app(TaskAssignees::class)->sync($task, [$this->ana->id, $this->ben->id]);
        Sanctum::actingAs($this->lead);

        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed', 'assignee_id' => $this->ana->id])->assertOk();

        $this->assertSame('Renamed', $task->fresh()->title);
        $this->assertSame([$this->ana->id, $this->ben->id], $this->ids($task));
    }

    public function test_an_older_client_reassigning_makes_one_assignee(): void
    {
        $task = $this->task(['assignee_id' => $this->ana->id]);
        app(TaskAssignees::class)->sync($task, [$this->ana->id, $this->ben->id]);
        Sanctum::actingAs($this->lead);

        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Shared work', 'assignee_id' => $this->ben->id])->assertOk();
        $this->assertSame([$this->ben->id], $this->ids($task));

        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Shared work', 'assignee_id' => null])->assertOk();
        $this->assertSame([], $this->ids($task));
    }

    public function test_saving_the_same_list_on_web_and_api_gives_the_same_result(): void
    {
        $web = $this->task(['title' => 'Web']);
        $api = $this->task(['title' => 'Api']);

        $this->actingAs($this->lead)
            ->put(route('tasks.update', $web), ['title' => 'Web', 'assignee_ids' => [$this->ben->id, $this->ana->id]])
            ->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->lead);
        $this->putJson("/api/v1/tasks/{$api->id}", ['title' => 'Api', 'assignee_ids' => [$this->ben->id, $this->ana->id]])->assertOk();

        $this->assertSame($this->ids($web), $this->ids($api));
        $this->assertSame($web->fresh()->assignee_id, $api->fresh()->assignee_id);
    }

    public function test_the_board_card_adds_a_second_person_on_web_and_api_alike(): void
    {
        $web = $this->task(['title' => 'Web', 'release_id' => $this->release->id, 'assignee_id' => $this->ana->id]);
        $api = $this->task(['title' => 'Api', 'release_id' => $this->release->id, 'assignee_id' => $this->ana->id]);

        $webJson = $this->actingAs($this->lead)
            ->patchJson(route('board.tasks.assign', $web), ['assignee_ids' => [$this->ana->id, $this->ben->id]])
            ->assertOk()->json();
        $this->assertSame([$this->ana->id, $this->ben->id], $webJson['assignee_ids']);
        $this->assertStringContainsString('Ana Dev', $webJson['html']);
        $this->assertStringContainsString('+1', $webJson['html']);
        $webRefused = $this->patchJson(route('board.tasks.assign', $web), ['assignee_ids' => [$this->ana->id, $this->outsider->id]])
            ->assertStatus(422)->json('errors');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->lead);
        $this->getJson("/api/v1/board/tasks/{$api->id}/assignees")->assertOk()->assertJsonPath('data.assignee_ids', [$this->ana->id]);
        $this->patchJson("/api/v1/board/tasks/{$api->id}/assignee", ['assignee_ids' => [$this->ana->id, $this->ben->id]])
            ->assertOk()->assertJsonPath('data.assignee_ids', [$this->ana->id, $this->ben->id]);
        $apiRefused = $this->patchJson("/api/v1/board/tasks/{$api->id}/assignee", ['assignee_ids' => [$this->ana->id, $this->outsider->id]])
            ->assertStatus(422)->json('errors');

        $this->assertSame($this->ids($web), $this->ids($api));
        $this->assertSame($webRefused, $apiRefused);
        $this->assertSame($this->ana->id, $web->fresh()->assignee_id);
        $this->assertSame('todo', $web->fresh()->status);
    }

    public function test_the_board_assign_endpoint_needs_one_of_the_fields(): void
    {
        $task = $this->task(['assignee_id' => $this->ana->id]);

        $this->actingAs($this->lead)->patchJson(route('board.tasks.assign', $task), [])->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        $this->assertSame([$this->ana->id], $this->ids($task));
    }

    public function test_bulk_assign_hands_each_task_to_one_person(): void
    {
        $task = $this->task(['assignee_id' => $this->ana->id]);
        app(TaskAssignees::class)->sync($task, [$this->ana->id, $this->ben->id]);

        $this->actingAs($this->lead)->post(route('tasks.bulk'), ['ids' => [$task->id], 'assignee_id' => $this->ana->id])->assertSessionHasNoErrors();

        $this->assertSame([$this->ana->id], $this->ids($task));
    }

    // ── Rules ──────────────────────────────────────────────────────────────

    public function test_a_developer_can_add_a_teammate_but_not_someone_from_another_team(): void
    {
        $task = $this->task(['assignee_id' => $this->ana->id]);
        $this->actingAs($this->ana);

        $this->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids' => [$this->ana->id, $this->outsider->id]])
            ->assertSessionHasErrors('assignee_ids.1');
        $this->assertSame([$this->ana->id], $this->ids($task));

        $this->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids' => [$this->ana->id, $this->ben->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame([$this->ana->id, $this->ben->id], $this->ids($task));
    }

    public function test_someone_already_assigned_can_stay_after_leaving_the_team(): void
    {
        $task = $this->task(['assignee_id' => $this->ana->id]);
        app(TaskAssignees::class)->sync($task, [$this->ana->id, $this->ben->id]);
        $this->core->members()->detach($this->ben);

        $this->actingAs($this->ana)
            ->put(route('tasks.update', $task), ['title' => 'Renamed', 'assignee_ids' => [$this->ana->id, $this->ben->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$this->ana->id, $this->ben->id], $this->ids($task));
    }

    public function test_a_release_task_only_takes_people_on_the_release_team(): void
    {
        $task = $this->task(['release_id' => $this->release->id, 'assignee_id' => $this->ana->id]);

        $this->actingAs($this->lead)
            ->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids' => [$this->ana->id, $this->outsider->id]])
            ->assertSessionHasErrors('assignee_ids.1');
    }

    public function test_the_board_refuses_a_newly_added_deactivated_person(): void
    {
        $task = $this->task(['assignee_id' => $this->ana->id]);
        $this->ben->forceFill(['deactivated_at' => now()])->save();

        $this->actingAs($this->lead)
            ->patchJson(route('board.tasks.assign', $task), ['assignee_ids' => [$this->ana->id, $this->ben->id]])
            ->assertStatus(422)->assertJsonValidationErrors('assignee_ids.1');
    }

    // ── Filters and visibility ─────────────────────────────────────────────

    public function test_my_tasks_and_the_assignee_filter_match_a_co_assignee(): void
    {
        $shared = $this->task(['assignee_id' => $this->ana->id, 'title' => 'Shared']);
        app(TaskAssignees::class)->sync($shared, [$this->ana->id, $this->ben->id]);
        $this->task(['assignee_id' => $this->ana->id, 'title' => 'Ana only']);
        $this->task(['title' => 'Nobody']);

        // Ben is QA, so a bare visit opens on his own tasks.
        $board = collect($this->actingAs($this->ben)->get(route('board.index'))->assertOk()->viewData('columns'))->flatten()->pluck('title')->all();
        $this->assertSame(['Shared'], $board);
        $list = $this->get(route('tasks.index'))->assertOk()->viewData('tasks')->pluck('title')->all();
        $this->assertSame(['Shared'], $list);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->lead);
        $this->assertEqualsCanonicalizing(['Shared'], collect($this->getJson("/api/v1/tasks?assignee_id={$this->ben->id}")->json('data'))->pluck('title')->all());
        $this->assertEqualsCanonicalizing(['Nobody'], collect($this->getJson('/api/v1/tasks?assignee_id=none')->json('data'))->pluck('title')->all());
    }

    public function test_a_developer_from_another_team_sees_a_task_they_were_added_to(): void
    {
        $task = $this->task(['assignee_id' => $this->ana->id]);
        app(TaskAssignees::class)->sync($task, [$this->ana->id, $this->outsider->id], actor: $this->lead);

        $this->actingAs($this->outsider)->get(route('tasks.show', $task))->assertOk();
        $this->assertTrue($task->fresh()->isVisibleTo($this->outsider));
        $this->assertTrue(Task::visibleTo($this->outsider)->whereKey($task->id)->exists());
    }

    // ── Notifications ──────────────────────────────────────────────────────

    public function test_each_new_assignee_is_told_once_and_nobody_else(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task(['assignee_id' => $this->ana->id]);
        $this->assertSame(['assigned'], $this->kinds($this->ana));
        $this->clearInboxes();

        $this->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids' => [$this->ana->id, $this->ben->id]]);
        $this->assertSame(['assigned'], $this->kinds($this->ben));
        $this->assertSame([], $this->kinds($this->ana));

        // Re-saving, or saving the lead alone as an older client would, says nothing.
        $this->clearInboxes();
        $this->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids' => [$this->ana->id, $this->ben->id]]);
        $this->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_id' => $this->ana->id]);
        $this->assertSame([], $this->kinds($this->ana));
        $this->assertSame([], $this->kinds($this->ben));
    }

    public function test_creating_with_two_people_tells_each_once(): void
    {
        Sanctum::actingAs($this->lead);
        $this->postJson('/api/v1/tasks', ['title' => 'Pair', 'team_id' => $this->core->id, 'assignee_ids' => [$this->ana->id, $this->ben->id]])->assertCreated();

        $this->assertSame(['assigned'], $this->kinds($this->ana));
        $this->assertSame(['assigned'], $this->kinds($this->ben));
    }

    public function test_someone_removed_is_told_and_the_rest_hear_nothing(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task(['assignee_id' => $this->ana->id]);
        $this->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids' => [$this->ana->id, $this->ben->id]]);
        $this->clearInboxes();

        $this->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids' => [$this->ana->id]]);

        $this->assertSame(['unassigned'], $this->kinds($this->ben));
        $this->assertStringContainsString('took you off', $this->ben->notifications()->first()->data['message']);
        $this->assertSame([], $this->kinds($this->ana));
    }

    public function test_adding_yourself_isnt_news(): void
    {
        $task = $this->task(['assignee_id' => $this->ana->id]);
        $this->actingAs($this->ben)
            ->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids' => [$this->ana->id, $this->ben->id]]);

        $this->assertSame([], $this->kinds($this->ben));
    }

    public function test_task_notices_reach_every_assignee(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task(['assignee_id' => $this->ana->id]);
        $this->put(route('tasks.update', $task), ['title' => 'Shared work', 'assignee_ids' => [$this->ana->id, $this->ben->id]]);
        $this->clearInboxes();

        // A due date moved in the same save as adding someone: the newcomer only hears they were added.
        $this->put(route('tasks.update', $task), [
            'title' => 'Shared work', 'due_date' => '2026-11-20',
            'assignee_ids' => [$this->ana->id, $this->ben->id, $this->member($this->core, 'Cat Dev')->id],
        ]);
        $cat = User::where('name', 'Cat Dev')->first();
        $this->assertSame(['due_changed'], $this->kinds($this->ana));
        $this->assertSame(['due_changed'], $this->kinds($this->ben));
        $this->assertSame(['assigned'], $this->kinds($cat));

        $this->clearInboxes();
        $this->post(route('tasks.comments.store', $task), ['body' => 'Status?']);
        $this->assertContains('commented', $this->kinds($this->ana));
        $this->assertContains('commented', $this->kinds($this->ben));
        $this->assertSame(1, Comment::count());
    }

    public function test_due_reminders_reach_every_assignee(): void
    {
        $task = $this->task(['assignee_id' => $this->ana->id, 'due_date' => today()->addDay()]);
        app(TaskAssignees::class)->sync($task, [$this->ana->id, $this->ben->id]);

        $this->artisan('notifications:due-reminders')->assertSuccessful();

        $this->assertSame(['due_tomorrow'], $this->kinds($this->ana));
        $this->assertSame(['due_tomorrow'], $this->kinds($this->ben));
    }
}
